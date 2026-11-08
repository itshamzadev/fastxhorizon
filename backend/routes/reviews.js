const express = require('express');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Review = require('../models/review');
const Order = require('../models/order');
const User = require('../models/user');
const AdminAuditLog = require('../models/adminAuditLog');
const auth = require('../middleware/auth');
const { requireAdminSession, requireAdminPermission } = require('../middleware/adminAuth');

const router = express.Router();
const reviewAttempts = new Map();
const rateLimitReview = (req, res, next) => {
  const key = String(req.user?.id || req.ip || 'unknown'); const now = Date.now(); const recent = (reviewAttempts.get(key) || []).filter(time => now - time < 60 * 60 * 1000);
  if (recent.length >= 5) return res.status(429).json({ message: 'Review submission limit reached. Please try again later.' });
  recent.push(now); reviewAttempts.set(key, recent); return next();
};
const clean = (value, max) => String(value || '').trim().slice(0, max);
const publicReview = item => ({ id: String(item._id), rating: item.rating, title: item.title, comment: item.comment, customerName: item.customerId?.name || 'Customer', verifiedPurchase: Boolean(item.orderId), createdAt: item.createdAt });
async function refreshProductRating(productId) {
  const [summary] = await Review.aggregate([{ $match: { productId: new mongoose.Types.ObjectId(productId), status: 'APPROVED' } }, { $group: { _id: null, average: { $avg: '$rating' }, count: { $sum: 1 } } }]);
  await Product.updateOne({ _id: productId }, { $set: { rating: summary ? Math.round(summary.average * 100) / 100 : 0, numReviews: summary?.count || 0, updatedAt: new Date() } });
}

router.get('/product/:productId', async (req, res) => {
  if (!mongoose.isValidObjectId(req.params.productId)) return res.status(400).json({ message: 'Invalid product.' });
  const page = Math.max(1, Number.parseInt(req.query.page, 10) || 1);
  const limit = Math.min(20, Math.max(1, Number.parseInt(req.query.limit, 10) || 10));
  const filter = { productId: req.params.productId, status: 'APPROVED' };
  const [items, total, distribution] = await Promise.all([
    Review.find(filter).populate('customerId', 'name').sort({ createdAt: -1, _id: 1 }).skip((page - 1) * limit).limit(limit).lean(),
    Review.countDocuments(filter),
    Review.aggregate([{ $match: filter }, { $group: { _id: '$rating', count: { $sum: 1 } } }, { $sort: { _id: -1 } }]),
  ]);
  const summary = distribution.reduce((result, row) => { result[row._id] = row.count; result.total += row.count; result.sum += row._id * row.count; return result; }, { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, total: 0, sum: 0 });
  return res.json({ data: { reviews: items.map(publicReview), total, page, limit, totalPages: Math.ceil(total / limit), hasMore: page * limit < total, average: summary.total ? Math.round((summary.sum / summary.total) * 100) / 100 : 0, distribution: { 1: summary[1], 2: summary[2], 3: summary[3], 4: summary[4], 5: summary[5] } } });
});

router.post('/', auth, rateLimitReview, async (req, res) => {
  const { productId, rating, title, comment } = req.body || {};
  if (!mongoose.isValidObjectId(productId) || !Number.isInteger(Number(rating)) || Number(rating) < 1 || Number(rating) > 5 || !clean(comment, 2000)) return res.status(400).json({ message: 'Product, rating from 1 to 5 and review comment are required.' });
  const customerId = req.user?.id || req.user?._id;
  const product = await Product.findOne({ _id: productId, status: 'active' }).lean();
  if (!product) return res.status(404).json({ message: 'Product not found.' });
  const user = await User.findById(customerId).select('email').lean();
  const eligible = user && await Order.findOne({ email: user.email, paymentStatus: 'verified', 'items.productId': productId, statusHistory: { $elemMatch: { code: { $in: ['DELIVERED', 'DELIVERY_CONFIRMED'] } } } }).select('_id').lean();
  try {
    const review = await Review.create({ productId, customerId, orderId: eligible?._id || null, rating: Number(rating), title: clean(title, 120), comment: clean(comment, 2000) });
    return res.status(201).json({ data: { id: String(review._id), status: review.status, verifiedPurchase: Boolean(review.orderId) } });
  } catch (error) {
    if (error.code === 11000) return res.status(409).json({ message: 'You have already reviewed this product.' });
    return res.status(500).json({ message: 'Unable to submit review.' });
  }
});

router.patch('/:id', auth, async (req, res) => {
  const customerId = req.user?.id || req.user?._id;
  const review = await Review.findOne({ _id: req.params.id, customerId, status: { $in: ['PENDING', 'REJECTED'] } });
  if (!review) return res.status(404).json({ message: 'Review cannot be edited.' });
  if (req.body.rating !== undefined) review.rating = Number(req.body.rating);
  if (req.body.title !== undefined) review.title = clean(req.body.title, 120);
  if (req.body.comment !== undefined) review.comment = clean(req.body.comment, 2000);
  review.status = 'PENDING'; review.rejectionReason = '';
  await review.save();
  return res.json({ data: { id: String(review._id), status: review.status } });
});

router.delete('/:id', auth, async (req, res) => {
  const result = await Review.deleteOne({ _id: req.params.id, customerId: req.user?.id || req.user?._id, status: { $in: ['PENDING', 'REJECTED'] } });
  if (!result.deletedCount) return res.status(404).json({ message: 'Review cannot be deleted.' });
  return res.status(204).send();
});

const admin = express.Router();
admin.use(requireAdminSession, requireAdminPermission('reviews.manage'));
admin.get('/', async (req, res) => {
  const page = Math.max(1, Number.parseInt(req.query.page, 10) || 1); const limit = Math.min(50, Math.max(1, Number.parseInt(req.query.limit, 10) || 20));
  const filter = {}; if (['PENDING', 'APPROVED', 'REJECTED', 'HIDDEN'].includes(req.query.status)) filter.status = req.query.status;
  if (req.query.rating) filter.rating = Number(req.query.rating);
  const search = clean(req.query.search, 100); if (search) filter.$or = [{ title: { $regex: search, $options: 'i' } }, { comment: { $regex: search, $options: 'i' } }];
  const [items, total, counts] = await Promise.all([Review.find(filter).populate('productId', 'name sku').populate('customerId', 'name email').sort({ createdAt: -1 }).skip((page - 1) * limit).limit(limit).lean(), Review.countDocuments(filter), Review.aggregate([{ $group: { _id: '$status', count: { $sum: 1 } } }])]);
  return res.json({ data: { reviews: items, total, page, limit, totalPages: Math.ceil(total / limit), hasMore: page * limit < total, counts: counts.reduce((out, row) => { out[row._id] = row.count; return out; }, {}) } });
});
admin.patch('/:id/moderation', async (req, res) => {
  const status = String(req.body.status || ''); if (!['APPROVED', 'REJECTED', 'HIDDEN'].includes(status)) return res.status(400).json({ message: 'Invalid moderation status.' });
  const review = await Review.findById(req.params.id); if (!review) return res.status(404).json({ message: 'Review not found.' });
  review.status = status; review.rejectionReason = status === 'REJECTED' ? clean(req.body.reason, 500) : ''; review.moderatedBy = req.admin._id; review.moderatedAt = new Date(); review.moderationHistory.push({ status, reason: review.rejectionReason, changedBy: req.admin._id }); await review.save();
  await refreshProductRating(review.productId);
  await AdminAuditLog.create({ adminId: req.admin._id, email: req.admin.email, action: `review_${status.toLowerCase()}`, success: true, ip: req.ip, metadata: { reviewId: String(review._id), productId: String(review.productId) } });
  return res.json({ data: { id: String(review._id), status } });
});

router.admin = admin;
module.exports = router;
