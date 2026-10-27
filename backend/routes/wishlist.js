const express = require('express');
const mongoose = require('mongoose');
const User = require('../models/user');
const Product = require('../models/product');
const auth = require('../middleware/auth');

const router = express.Router();
const MAX_WISHLIST_SIZE = 100;

function validId(value) {
  return mongoose.Types.ObjectId.isValid(String(value || ''));
}

async function productsForIds(ids) {
  const uniqueIds = Array.from(new Set(ids.map(String)));
  const products = await Product.find({ _id: { $in: uniqueIds }, status: { $ne: 'archived' } }).lean();
  const byId = new Map(products.map(product => [String(product._id), product]));
  return uniqueIds.map(id => byId.get(id)).filter(Boolean);
}

router.use(auth);

router.get('/', async (req, res) => {
  try {
    const user = await User.findById(req.user.id).select('wishlist').lean();
    if (!user) return res.status(401).json({ msg: 'User not found' });
    return res.json(await productsForIds(user.wishlist || []));
  } catch (error) {
    console.error('Wishlist fetch failed:', error.message);
    return res.status(500).json({ msg: 'Unable to load wishlist' });
  }
});

router.post('/merge', async (req, res) => {
  try {
    const requested = Array.isArray(req.body?.productIds) ? req.body.productIds : [];
    const ids = Array.from(new Set(requested.filter(validId).map(String))).slice(0, MAX_WISHLIST_SIZE);
    await User.findByIdAndUpdate(req.user.id, { $addToSet: { wishlist: { $each: ids } } }, { runValidators: true });
    const user = await User.findById(req.user.id).select('wishlist').lean();
    return res.json(await productsForIds(user?.wishlist || []));
  } catch (error) {
    console.error('Wishlist merge failed:', error.message);
    return res.status(500).json({ msg: 'Unable to merge wishlist' });
  }
});

router.post('/:id', async (req, res) => {
  try {
    if (!validId(req.params.id)) return res.status(400).json({ msg: 'Invalid product id' });
    const product = await Product.findOne({ _id: req.params.id, status: { $ne: 'archived' } }).lean();
    if (!product) return res.status(404).json({ msg: 'Product not found' });
    await User.findByIdAndUpdate(req.user.id, { $addToSet: { wishlist: product._id } }, { runValidators: true });
    return res.json(product);
  } catch (error) {
    console.error('Wishlist add failed:', error.message);
    return res.status(500).json({ msg: 'Unable to update wishlist' });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    if (!validId(req.params.id)) return res.status(400).json({ msg: 'Invalid product id' });
    await User.findByIdAndUpdate(req.user.id, { $pull: { wishlist: req.params.id } });
    return res.status(204).send();
  } catch (error) {
    console.error('Wishlist remove failed:', error.message);
    return res.status(500).json({ msg: 'Unable to update wishlist' });
  }
});

module.exports = router;
