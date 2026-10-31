const express = require('express');
const router = express.Router();
const Product = require('../models/product');

const MAX_QUERY_LENGTH = 100;
const MAX_PAGE_SIZE = 24;
const MAX_MATCHES_FOR_RANKING = 200;
const PUBLIC_FIELDS = 'name description shortDescription category subcategory categoryPath productType tags slug image images price currency brand sku storeName stock inventoryMode availabilityStatus rating numReviews createdAt specifications attributes physicalConfiguration digitalConfiguration';
const escapeRegex = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const normalizeQuery = value => String(value || '').trim().slice(0, MAX_QUERY_LENGTH);
const searchableFields = ['name', 'description', 'shortDescription', 'category', 'subcategory', 'categoryPath', 'productType', 'tags', 'brand', 'sku'];

const publicProduct = product => ({
  id: product._id,
  name: product.name,
  description: product.description || '',
  shortDescription: product.shortDescription || product.description || '',
  category: product.category,
  subcategory: product.subcategory || '',
  categoryPath: product.categoryPath || [],
  productType: product.productType || '',
  tags: product.tags || [],
  slug: product.slug,
  image: product.image || (Array.isArray(product.images) ? product.images[0] : ''),
  images: Array.isArray(product.images) ? product.images : [],
  price: product.price,
  currency: product.currency || 'PKR',
  brand: product.brand || '',
  sku: product.sku || '',
  storeName: product.storeName || 'FASTXHORIZON',
  stock: product.stock,
  inventoryMode: product.inventoryMode,
  availabilityStatus: product.availabilityStatus,
  rating: product.rating || 0,
  numReviews: product.numReviews || 0,
  createdAt: product.createdAt,
  productType: product.productType === 'DIGITAL' ? 'DIGITAL' : 'PHYSICAL',
  specifications: Array.isArray(product.specifications) ? product.specifications.filter(item => item.public !== false) : [],
  attributes: Array.isArray(product.attributes) ? product.attributes.filter(item => item.public !== false) : [],
  physicalConfiguration: product.productType === 'DIGITAL' || !product.physicalConfiguration ? null : { weight: product.physicalConfiguration.weight, weightUnit: product.physicalConfiguration.weightUnit, dimensions: product.physicalConfiguration.dimensions, warranty: product.physicalConfiguration.warranty, condition: product.physicalConfiguration.condition, shippingInfo: product.physicalConfiguration.shippingInfo, ownershipMode: product.physicalConfiguration.ownershipMode },
  digitalConfiguration: product.productType === 'DIGITAL' && product.digitalConfiguration ? { deliveryType: product.digitalConfiguration.deliveryType, version: product.digitalConfiguration.version, releaseNotes: product.digitalConfiguration.releaseNotes, supportedPlatforms: product.digitalConfiguration.supportedPlatforms, systemRequirements: product.digitalConfiguration.systemRequirements, licenseType: product.digitalConfiguration.licenseType, downloadLimit: product.digitalConfiguration.downloadLimit, accessDurationDays: product.digitalConfiguration.accessDurationDays, updatePolicy: product.digitalConfiguration.updatePolicy, instructions: product.digitalConfiguration.instructions, documentationUrl: product.digitalConfiguration.documentationUrl } : null,
});

const scoreProduct = (product, terms) => {
  const name = String(product.name || '').toLowerCase();
  const haystack = searchableFields.map(field => Array.isArray(product[field]) ? product[field].join(' ') : String(product[field] || '')).join(' ').toLowerCase();
  return terms.reduce((score, term) => score + (name === term ? 100 : 0) + (name.includes(term) ? 25 : 0) + (haystack.includes(term) ? 5 : 0), 0);
};

/**
 * @swagger
 * /api/search:
 *   get:
 *     summary: Search for products
 *     description: Retrieve a list of products that match the provided search query in their name or description. If no query is provided, it returns all products.
 *     tags:
 *       - Search
 *     parameters:
 *       - in: query
 *         name: q
 *         schema:
 *           type: string
 *         required: false
 *         description: The search query to match against product names or descriptions.
 *     responses:
 *       200:
 *         description: A list of products matching the search query.
 *         content:
 *           application/json:
 *             schema:
 *               type: array
 *               items:
 *                 type: object
 *                 properties:
 *                   _id:
 *                     type: string
 *                     description: The unique identifier for the product.
 *                   name:
 *                     type: string
 *                     description: The name of the product.
 *                   description:
 *                     type: string
 *                     description: The description of the product.
 *                   price:
 *                     type: number
 *                     format: float
 *                     description: The price of the product.
 *                   category:
 *                     type: string
 *                     description: The category of the product.
 *                   createdAt:
 *                     type: string
 *                     format: date-time
 *                     description: The date the product was created.
 *                   updatedAt:
 *                     type: string
 *                     format: date-time
 *                     description: The date the product was last updated.
 *       500:
 *         description: An internal server error occurred.
 *         content:
 *           application/json:
 *             schema:
 *               type: object
 *               properties:
 *                 error:
 *                   type: string
 *                   description: A message describing the error.
 */
router.get('/', async (req, res) => {
  try {
    const query = normalizeQuery(req.query.q);
    const page = Math.max(1, Number.parseInt(req.query.page, 10) || 1);
    const requestedLimit = Number.parseInt(req.query.limit, 10) || 12;
    const limit = Math.min(MAX_PAGE_SIZE, Math.max(1, requestedLimit));
    if (!query) return res.json({ products: [], total: 0, page, pageSize: limit, totalPages: 0, query: '' });

    const terms = query.toLowerCase().split(/\s+/).filter(Boolean).map(escapeRegex);
    const filter = {
      status: 'active',
      price: { $gt: 0 },
      $or: [{ image: { $type: 'string', $ne: '' } }, { images: { $elemMatch: { $type: 'string', $ne: '' } } }],
      $and: terms.map(term => ({ $or: searchableFields.map(field => ({ [field]: { $regex: term, $options: 'i' } })) })),
    };
    const queryBuilder = Product.find(filter);
    const matches = typeof queryBuilder.select === 'function'
      ? await queryBuilder.select(PUBLIC_FIELDS).limit(MAX_MATCHES_FOR_RANKING).lean()
      : await queryBuilder;
    const ranked = matches
      .map(product => ({ product, score: scoreProduct(product, terms) }))
      .sort((a, b) => b.score - a.score || new Date(b.product.createdAt || 0) - new Date(a.product.createdAt || 0))
      .map(entry => entry.product);
    const total = ranked.length;
    const start = (page - 1) * limit;
    res.json({ products: ranked.slice(start, start + limit).map(publicProduct), total, page, pageSize: limit, totalPages: Math.ceil(total / limit), query });
  } catch (error) {
    console.error('Error searching products:', error);
    res.status(500).json({ error: 'An error occurred during the search.' });
  }
});

module.exports = router;
