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

const ProductSpecificationSchema = new mongoose.Schema({
    key: { type: String, required: true, trim: true, maxlength: 60 },
    label: { type: String, required: true, trim: true, maxlength: 80 },
    value: { type: String, required: true, trim: true, maxlength: 500 },
    type: { type: String, enum: ['text', 'long_text', 'number', 'decimal', 'boolean', 'url', 'measurement', 'date'], default: 'text' },
    unit: { type: String, trim: true, maxlength: 20, default: '' },
    public: { type: Boolean, default: true },
    order: { type: Number, min: 0, max: 999, default: 0 },
}, { _id: false });

const ProductAttributeSchema = new mongoose.Schema({
    key: { type: String, required: true, trim: true, lowercase: true, maxlength: 60 },
    label: { type: String, required: true, trim: true, maxlength: 80 },
    type: { type: String, enum: ['text', 'long_text', 'number', 'decimal', 'boolean', 'dropdown', 'multi_select', 'date', 'url', 'measurement', 'color'], default: 'text' },
    required: { type: Boolean, default: false },
    allowedValues: { type: [String], default: [] },
    defaultValue: { type: String, trim: true, default: '' },
    unit: { type: String, trim: true, maxlength: 20, default: '' },
    public: { type: Boolean, default: true },
    variant: { type: Boolean, default: false },
    order: { type: Number, min: 0, max: 999, default: 0 },
}, { _id: false });

const PhysicalConfigurationSchema = new mongoose.Schema({
    weight: { type: Number, min: 0, default: null },
    weightUnit: { type: String, enum: ['g', 'kg', 'oz', 'lb'], default: 'g' },
    dimensions: { length: { type: Number, min: 0, default: null }, width: { type: Number, min: 0, default: null }, height: { type: Number, min: 0, default: null }, unit: { type: String, enum: ['mm', 'cm', 'm', 'in'], default: 'cm' } },
    warranty: { type: String, trim: true, maxlength: 200, default: '' },
    condition: { type: String, enum: ['new', 'used', 'refurbished', 'other'], default: 'new' },
    shippingInfo: { type: String, trim: true, maxlength: 500, default: '' },
    trackingEnabled: { type: Boolean, default: false },
    lowStockThreshold: { type: Number, min: 0, default: 0 },
    supplierId: { type: mongoose.Schema.Types.ObjectId, ref: 'Supplier', default: null },
    ownershipMode: { type: String, enum: ['PURCHASED', 'CONSIGNMENT', 'RETURNABLE_PURCHASE', 'OTHER_AGREED_TERMS'], default: 'PURCHASED' },
}, { _id: false });

const DigitalConfigurationSchema = new mongoose.Schema({
    deliveryType: { type: String, enum: ['DOWNLOAD', 'LICENSE', 'EXTERNAL_ACCESS', 'MANUAL'], default: 'DOWNLOAD' },
    version: { type: String, trim: true, maxlength: 60, default: '' },
    releaseNotes: { type: String, trim: true, maxlength: 3000, default: '' },
    supportedPlatforms: { type: [String], default: [] },
    systemRequirements: { type: String, trim: true, maxlength: 3000, default: '' },
    licenseType: { type: String, trim: true, maxlength: 80, default: '' },
    downloadLimit: { type: Number, min: 1, default: null },
    accessDurationDays: { type: Number, min: 1, default: null },
    updatePolicy: { type: String, trim: true, maxlength: 500, default: '' },
    instructions: { type: String, trim: true, maxlength: 3000, default: '' },
    documentationUrl: { type: String, trim: true, maxlength: 500, default: '' },
}, { _id: false });

const ProductSeoSchema = new mongoose.Schema({
    title: { type: String, trim: true, maxlength: 160, default: '' },
    description: { type: String, trim: true, maxlength: 320, default: '' },
    canonicalPath: { type: String, trim: true, maxlength: 300, default: '' },
}, { _id: false });

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
    productType: { type: String, enum: ['PHYSICAL', 'DIGITAL'], default: 'PHYSICAL' },
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
    specifications: { type: [ProductSpecificationSchema], default: [] },
    attributes: { type: [ProductAttributeSchema], default: [] },
    physicalConfiguration: { type: PhysicalConfigurationSchema, default: () => ({}) },
    digitalConfiguration: { type: DigitalConfigurationSchema, default: null },
    seo: { type: ProductSeoSchema, default: () => ({}) },
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
    sourceParent: { type: String, trim: true, default: '' },
    sourceFormat: { type: String, trim: true, default: '' },
    sourcePublished: { type: String, trim: true, default: '' },
    sourceInStock: { type: Boolean, default: false },
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
