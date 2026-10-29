const express = require('express');
const { randomInt } = require('crypto');
const mongoose = require('mongoose');
const Product = require('../models/product');
const Order = require('../models/order');
const PaymentConfiguration = require('../models/paymentConfiguration');
const PaymentSubmission = require('../models/paymentSubmission');
const auth = require('../middleware/auth');

const router = express.Router();

const STATUS_FLOW = Order.STATUS_FLOW || [];

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

async function generateOrderNumber() {
  for (let attempt = 0; attempt < 6; attempt += 1) {
    const candidate = `FE-${randomInt(100000, 999999)}`;
    // eslint-disable-next-line no-await-in-loop
    const exists = await Order.exists({ orderNumber: candidate });
    if (!exists) {
      return candidate;
    }
  }
  return `FE-${Date.now()}`;
}

/**
 * @swagger
 * /api/checkout/create-order:
 *   post:
 *     summary: Create a new order
 *     description: Creates a new order with the provided details such as items, customer information, and payment details.
 *     tags:
 *       - Orders
 *     requestBody:
 *       required: true
 *       content:
 *         application/json:
 *           schema:
 *             type: object
 *             properties:
 *               items:
 *                 type: array
 *                 items:
 *                   type: object
 *                   properties:
 *                     productId:
 *                       type: string
 *                       description: The unique identifier of the product.
 *                     quantity:
 *                       type: integer
 *                       description: The number of items for this product.
 *               name:
 *                 type: string
 *                 description: Customer's name.
 *               email:
 *                 type: string
 *                 description: Customer's email address.
 *               shippingAddress:
 *                 type: string
 *                 description: Customer's shipping address.
 *               cardNumber:
 *                 type: string
 *                 description: Customer's card number.
 *               cardName:
 *                 type: string
 *                 description: Name on the customer's card.
 *               expiry:
 *                 type: string
 *                 description: Card expiry date in MM/YY format.
 *               cvc:
 *                 type: string
 *                 description: Card CVC.
 *     responses:
 *       201:
 *         description: Order created successfully
 *         content:
 *           application/json:
 *             schema:
 *               type: object
 *               properties:
 *                 message:
 *                   type: string
 *                 orderNumber:
 *                   type: string
 *                   example: FE-482913
 *                 estimatedDelivery:
 *                   type: string
 *                   format: date-time
 *                 statusHistory:
 *                   type: array
 *                   items:
 *                     $ref: '#/components/schemas/OrderStatus'
 *                 statusFlow:
 *                   type: array
 *                   items:
 *                     $ref: '#/components/schemas/OrderStatus'
 *                 items:
 *                   type: array
 *                   items:
 *                     type: object
 *                     properties:
 *                       productId:
 *                         type: string
 *                       name:
 *                         type: string
 *                       price:
 *                         type: number
 *                       quantity:
 *                         type: integer
 *                 total:
 *                   type: number
 *                   format: float
 *       400:
 *         description: Bad request - Missing or invalid fields
 *         content:
 *           application/json:
 *             schema:
 *               type: object
 *               properties:
 *                 error:
 *                   type: string
 *                   description: Description of the error.
 *       500:
 *         description: Internal server error
 *         content:
 *           application/json:
 *             schema:
 *               type: object
 *               properties:
 *                 error:
 *                   type: string
 *                   description: Description of the server error.
 */
router.post('/create-order', auth, async (req, res) => {
  try {
    const { items, name, email, shippingAddress } = req.body;
    const paymentMethod = req.body?.paymentMethod;

    if (!Array.isArray(items) || !items.length || !name || !email || !shippingAddress || !['automatic', 'manual', 'cod'].includes(paymentMethod)) {
      return res.status(400).json({ error: 'Missing required fields' });
    }

    const trimmedEmail = String(email).trim();
    const trimmedName = String(name).trim();
    const trimmedAddress = String(shippingAddress).trim();

    if (!trimmedName || !trimmedAddress) {
      return res.status(400).json({ error: 'Name and shipping address are required.' });
    }

    if (!emailRegex.test(trimmedEmail)) {
      return res.status(400).json({ error: 'Invalid email format' });
    }

    const config = await PaymentConfiguration.findOne({ key: 'v1' }).lean();
    const enabled = paymentMethod === 'automatic' ? config?.automaticEnabled : paymentMethod === 'cod' ? config?.codEnabled : config?.manualEnabled;
    const method = paymentMethod === 'manual' ? (config?.manualMethods || []).find(item => item.key === req.body?.methodKey && item.enabled) : null;
    if (!enabled || (paymentMethod === 'manual' && !method)) return res.status(400).json({ error: 'Selected payment type is currently unavailable.' });
    if (config?.ordersEnabled !== true) {
      return res.status(503).json({ error: config?.orderingDisabledMessage || 'Online ordering is temporarily unavailable.' });
    }
    if (paymentMethod === 'automatic') return res.status(400).json({ error: 'Automatic payment is not operational yet.' });
    if (paymentMethod === 'manual' && (!req.body?.paymentValues || !req.body?.paymentEvidence)) return res.status(400).json({ error: 'Manual payment details are required.' });
    if (paymentMethod === 'manual') {
      const values = req.body.paymentValues || {};
      for (const field of (method.fields || []).filter(item => item.enabled)) {
        if (field.required && field.type !== 'file' && (values[field.id] === undefined || values[field.id] === '')) return res.status(400).json({ error: `${field.label} is required.` });
        if (['select', 'radio'].includes(field.type) && values[field.id] !== undefined && !(field.options || []).includes(String(values[field.id]))) return res.status(400).json({ error: `${field.label} has an invalid option.` });
      }
    }

    const normalizedItems = [];
    for (const item of items) {
      const productId = item?.productId || item?.id;
      const quantity = Number.isInteger(item?.quantity) && item.quantity > 0 ? item.quantity : 1;

      if (!productId || !mongoose.Types.ObjectId.isValid(productId)) {
        return res.status(400).json({ error: 'Invalid product reference in order payload.' });
      }

      normalizedItems.push({
        productId: String(productId),
        quantity,
      });
    }

    const productIds = [...new Set(normalizedItems.map(item => item.productId))];
    const products = await Product.find({ _id: { $in: productIds }, status: { $ne: 'archived' } }).lean();

    if (products.length !== productIds.length) {
      return res.status(400).json({ error: 'One or more products are no longer available.' });
    }

    const productMap = new Map(products.map(product => [String(product._id), product]));

    const orderItems = normalizedItems.map(item => {
      const product = productMap.get(item.productId);
      return {
        productId: product._id,
        name: product.name,
        price: product.price,
        quantity: item.quantity,
        image: product.image,
      };
    });

    const orderTotal = orderItems.reduce((sum, item) => sum + item.price * item.quantity, 0);

    // Reserve inventory using an atomic conditional update. Roll back any
    // earlier reservations if a later line cannot be fulfilled.
    const reserved = [];
    try {
      for (const item of normalizedItems) {
        const candidate = await Product.findById(item.productId).select('stock status inventoryMode availabilityStatus');
        const availabilityMode = ['AVAILABILITY_BASED', 'AVAILABILITY'].includes(candidate?.inventoryMode);
        const updated = candidate && candidate.status !== 'archived' && ((availabilityMode && candidate.availabilityStatus === 'IN_STOCK') || (!availabilityMode && candidate.stock >= item.quantity))
          ? (availabilityMode ? candidate : await Product.findOneAndUpdate({ _id: item.productId, stock: { $gte: item.quantity }, status: { $ne: 'archived' } }, { $inc: { stock: -item.quantity }, $set: { updatedAt: new Date() } }, { new: true }))
          : null;
        if (!updated) throw new Error('INSUFFICIENT_STOCK');
        reserved.push({ ...item, availabilityMode });
      }
    } catch (reservationError) {
      await Promise.all(reserved.filter(item => !item.availabilityMode).map(item => Product.updateOne({ _id: item.productId }, { $inc: { stock: item.quantity } })));
      return res.status(409).json({ error: reservationError.message === 'INSUFFICIENT_STOCK' ? 'One or more products do not have enough stock.' : 'Unable to reserve inventory.' });
    }

    const orderNumber = await generateOrderNumber();
    const order = new Order({
      orderNumber,
      email: trimmedEmail.toLowerCase(),
      name: trimmedName,
      shippingAddress: trimmedAddress,
      items: orderItems,
      total: orderTotal,
      currency: 'PKR',
      paymentMethod,
      paymentStatus: 'pending',
      paymentLifecycle: 'PENDING_PAYMENT_VERIFICATION',
      availabilityStatus: orderItems.some(item => productMap.get(String(item.productId))?.inventoryMode === 'AVAILABILITY_BASED') ? 'PENDING_CONFIRMATION' : 'CONFIRMED',
    });

    order.ensureInitialStatus();

    try {
      await order.save();
    } catch (saveError) {
      await Promise.all(reserved.filter(item => !item.availabilityMode).map(item => Product.updateOne({ _id: item.productId }, { $inc: { stock: item.quantity } })));
      throw saveError;
    }

    let paymentSubmission = null;
    if (paymentMethod === 'manual') {
      paymentSubmission = await PaymentSubmission.create({ orderId: order._id, orderNumber, email: order.email, methodKey: method.key, amount: orderTotal, currency: 'PKR', state: 'PENDING_VERIFICATION', methodSnapshot: method.toObject ? method.toObject() : method, values: req.body.paymentValues, evidence: req.body.paymentEvidence });
      order.paymentSubmissionId = paymentSubmission._id;
      await order.save();
    }

    await new Promise(resolve => setTimeout(resolve, 1200));

    res.status(201).json({
      message: 'Order created successfully!',
      orderNumber,
      paymentStatus: order.paymentStatus,
      paymentLifecycle: order.paymentLifecycle,
      availabilityStatus: order.availabilityStatus,
      paymentSubmissionState: paymentSubmission?.state || null,
      statusHistory: order.statusHistory,
      statusFlow: STATUS_FLOW,
      items: orderItems,
      total: orderTotal,
    });
  } catch (error) {
    console.error('Error creating order:', error);
    res.status(500).json({ error: 'Failed to create order' });
  }
});

module.exports = router;
