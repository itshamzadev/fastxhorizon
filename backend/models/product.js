const mongoose = require('mongoose');
const {
    ensureProductSyncedWithPinecone,
    removeProductFromPinecone,
} = require('../services/pineconeSync');

const VariantAttributeSchema = new mongoose.Schema({
    name: { type: String, required: true, trim: true, maxlength: 40 },
    type: { type: String, enum: ['color', 'size', 'unit', 'volume', 'weight', 'capacity', 'pack_size', 'storage', 'material', 'other'], default: 'other' },
    values: { type: [String], default: [] },
}, { _id: false });

const ProductVariantSchema = new mongoose.Schema({
    key: { type: String, required: true, trim: true, maxlength: 300 },
    options: { type: mongoose.Schema.Types.Mixed, default: {} },
    sku: { type: String, trim: true, uppercase: true, default: '' },
    price: { type: Number, required: true, min: 0 },
    compareAtPrice: { type: Number, min: 0, default: null },
    images: { type: [String], default: [] },
    inventoryMode: { type: String, enum: ['NUMERIC', 'AVAILABILITY_BASED', 'SUPPLIER_BASED'], default: 'NUMERIC' },
    stock: { type: Number, min: 0, default: 0 },
    availabilityStatus: { type: String, enum: ['IN_STOCK', 'OUT_OF_STOCK', 'AVAILABLE_TO_ORDER'], default: 'OUT_OF_STOCK' },
    enabled: { type: Boolean, default: true },
}, { _id: true });

const productSchema = new mongoose.Schema({
    name: {
        type: String,
        required: true,
        unique: true,
    },
    description: {
        type: String,
        required: true,
    },
    price: {
        type: Number,
        required: true,
        min: 0,
    },
    currency: { type: String, enum: ['PKR'], default: 'PKR' },
    category: {
        type: String,
        required: true,
    },
    subcategory: { type: String, trim: true, default: '' },
    categoryPath: { type: [String], default: [] },
    productType: { type: String, trim: true, default: '' },
    shortDescription: { type: String, trim: true, maxlength: 300, default: '' },
    longDescription: { type: String, trim: true, maxlength: 5000, default: '' },
    tags: { type: [String], default: [] },
    slug: { type: String, trim: true, lowercase: true, unique: true, sparse: true, index: true },
    storeName: { type: String, trim: true, default: 'FASTXHORIZON' },
    image: {
        type: String,
        default: '',
    },
    images: { type: [String], default: [] },
    variantsEnabled: { type: Boolean, default: false },
    variantAttributes: { type: [VariantAttributeSchema], default: [] },
    variants: { type: [ProductVariantSchema], default: [] },
    sku: { type: String, trim: true, uppercase: true, sparse: true, unique: true },
    brand: String,
    stock: {
        type: Number,
        default: 0,
    },
    inventoryMode: { type: String, enum: ['NUMERIC', 'AVAILABILITY_BASED', 'AVAILABILITY', 'SUPPLIER_BASED'], default: 'NUMERIC' },
    availabilityStatus: { type: String, enum: ['IN_STOCK', 'OUT_OF_STOCK', 'AVAILABLE_TO_ORDER'], default: 'OUT_OF_STOCK' },
    supplierKey: { type: String, trim: true, sparse: true, unique: true },
    supplierName: { type: String, trim: true, default: '' },
    supplierSKU: { type: String, trim: true, default: '' },
    sourceHandle: { type: String, trim: true, default: '' },
    sourceUrl: { type: String, trim: true, default: '' },
    sourcePrice: { type: Number, min: 0, default: null },
    confirmedSupplierCost: { type: Number, min: 0, default: null },
    operationalCostEstimate: { type: Number, min: 0, default: null },
    sellingPrice: { type: Number, min: 0, default: null },
    draftSellingPrice: { type: Number, min: 0, default: null },
    targetProfit: { type: Number, min: 0, default: null },
    estimatedNetProfit: { type: Number, default: null },
    pricingStatus: { type: String, enum: ['REVIEW_REQUIRED', 'APPROVED', 'MANUAL_OVERRIDE'], default: 'REVIEW_REQUIRED' },
    lastPriceReviewAt: { type: Date, default: null },
    status: { type: String, enum: ['active', 'draft', 'archived'], default: 'active' },
    featured: { type: Boolean, default: false },
    rating: {
        type: Number,
        default: 0,
        min: 0,
        max: 5,
    },
    numReviews: {
        type: Number,
        default: 0,
    },
    createdAt: {
        type: Date,
        default: Date.now,
    },
    updatedAt: {
        type: Date,
        default: Date.now,
    },
    weaviateId: {
        type: String,
        unique: true,
        sparse: true,
    },
    pineconeId: {
        type: String,
        unique: true,
        sparse: true,
    },
});

const PINECONE_SYNC_FIELDS = ['name', 'description', 'category', 'brand', 'price', 'image'];

productSchema.pre('save', function(next) {
    this.updatedAt = new Date();
    this._shouldSyncPinecone = this.isNew || PINECONE_SYNC_FIELDS.some(field => this.isModified(field));
    next();
});

productSchema.post('save', function(doc, next) {
    const shouldSync = doc._shouldSyncPinecone;
    delete doc._shouldSyncPinecone;
    if (!shouldSync) return next();

    ensureProductSyncedWithPinecone(doc)
        .catch(err => {
            console.error('Pinecone sync (save) failed:', err);
        })
        .finally(() => next());
});

productSchema.pre('findOneAndUpdate', function(next) {
    const update = this.getUpdate() || {};
    const directUpdates = { ...update, ...(update.$set || {}) };
    const keys = Object.keys(directUpdates);
    this._shouldSyncPinecone = PINECONE_SYNC_FIELDS.some(field => keys.includes(field));
    next();
});

productSchema.post('findOneAndUpdate', function(doc, next) {
    const shouldSync = this._shouldSyncPinecone;
    delete this._shouldSyncPinecone;
    if (!shouldSync || !doc) return next();

    ensureProductSyncedWithPinecone(doc)
        .catch(err => {
            console.error('Pinecone sync (update) failed:', err);
        })
        .finally(() => next());
});

productSchema.post('deleteOne', { document: true, query: false }, function(next) {
    const id = this && this._id;
    if (!id) return next();
    removeProductFromPinecone(id)
        .catch(err => {
            console.error('Pinecone removal (deleteOne) failed:', err);
        })
        .finally(() => next());
});

productSchema.post('findOneAndDelete', function(doc, next) {
    if (!doc) return next();
    removeProductFromPinecone(doc._id)
        .catch(err => {
            console.error('Pinecone removal (findOneAndDelete) failed:', err);
        })
        .finally(() => next());
});

const Product = mongoose.model('Product', productSchema);

module.exports = Product;
