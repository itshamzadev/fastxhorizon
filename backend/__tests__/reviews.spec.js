const express = require('express');
const jwt = require('jsonwebtoken');
const request = require('supertest');

process.env.JWT_SECRET = 'review-test-secret';
jest.mock('../models/product', () => ({ findOne: jest.fn(), updateOne: jest.fn() }));
jest.mock('../models/review', () => ({ find: jest.fn(), countDocuments: jest.fn(), aggregate: jest.fn(), create: jest.fn(), findOne: jest.fn(), findById: jest.fn(), deleteOne: jest.fn() }));
jest.mock('../models/order', () => ({ findOne: jest.fn() }));
jest.mock('../models/user', () => ({ findById: jest.fn() }));
jest.mock('../models/adminAuditLog', () => ({ create: jest.fn() }));

const Review = require('../models/review');
const Product = require('../models/product');
const User = require('../models/user');
const Order = require('../models/order');
const reviewsRouter = require('../routes/reviews');

const app = express(); app.use(express.json()); app.use('/api/reviews', reviewsRouter);
const token = jwt.sign({ user: { id: 'customer-1' } }, process.env.JWT_SECRET);

describe('secure customer reviews', () => {
  beforeEach(() => jest.clearAllMocks());

  test('guests can read public review summaries but cannot submit', async () => {
    const chain = { populate: jest.fn().mockReturnThis(), sort: jest.fn().mockReturnThis(), skip: jest.fn().mockReturnThis(), limit: jest.fn().mockReturnThis(), lean: jest.fn().mockResolvedValue([]) };
    Review.find.mockReturnValue(chain); Review.countDocuments.mockResolvedValue(0); Review.aggregate.mockResolvedValue([]);
    expect((await request(app).get('/api/reviews/product/507f1f77bcf86cd799439011')).status).toBe(200);
    expect((await request(app).post('/api/reviews').send({ productId: '507f1f77bcf86cd799439011', rating: 5, comment: 'x' })).status).toBe(401);
  });

  test('authenticated submission starts pending and does not trust client moderation fields', async () => {
    Product.findOne.mockReturnValue({ lean: jest.fn().mockResolvedValue({ _id: '507f1f77bcf86cd799439011', status: 'active' }) });
    User.findById.mockReturnValue({ select: jest.fn().mockReturnValue({ lean: jest.fn().mockResolvedValue({ email: 'customer@example.com' }) }) });
    Order.findOne.mockReturnValue({ select: jest.fn().mockReturnValue({ lean: jest.fn().mockResolvedValue(null) }) });
    Review.create.mockResolvedValue({ _id: 'review-1', status: 'PENDING', orderId: null });
    const response = await request(app).post('/api/reviews').set('x-auth-token', token).send({ productId: '507f1f77bcf86cd799439011', rating: 5, comment: 'Good', status: 'APPROVED', verifiedPurchase: true });
    expect(response.status).toBe(201); expect(Review.create).toHaveBeenCalledWith(expect.objectContaining({ orderId: null, customerId: 'customer-1' }));
  });

  test('unauthenticated edit and delete are rejected', async () => {
    expect((await request(app).patch('/api/reviews/507f1f77bcf86cd799439011').send({ comment: 'x' })).status).toBe(401);
    expect((await request(app).delete('/api/reviews/507f1f77bcf86cd799439011')).status).toBe(401);
  });
});
