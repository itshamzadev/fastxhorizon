const { groupProducts, parseCsv, draftPricing, categoryFor, cleanHtml } = require('../scripts/import-yourmart-csv');

const SUPPLIER = 'YOURMART';

function safeFilename(value) {
  return String(value || 'catalog.csv').replace(/[^a-zA-Z0-9._-]/g, '_').slice(0, 255) || 'catalog.csv';
}

function buildEntries(csvText) {
  if (typeof csvText !== 'string' || Buffer.byteLength(csvText, 'utf8') > 10 * 1024 * 1024) {
    throw new Error('CSV must be valid text and no larger than 10 MB.');
  }
  const rows = parseCsv(csvText);
  const products = groupProducts(rows);
  const entries = products.map((item) => {
    const category = categoryFor(item);
    const pricing = draftPricing(item.sourcePrice);
    return {
      item,
      category,
      pricing,
      supplierKey: `${SUPPLIER}:${item.handle}`,
      preview: {
        supplierKey: `${SUPPLIER}:${item.handle}`,
        name: item.title,
        category: category.parent,
        subcategory: category.child,
        supplierSKU: item.supplierSKU,
        sourcePrice: item.sourcePrice,
        draftSellingPrice: pricing.draftSellingPrice,
        image: item.images[0] || null,
        imageCount: item.images.length,
        pricingStatus: 'REVIEW_REQUIRED',
        validation: item.title && item.images.length && item.sourcePrice !== null ? 'valid' : 'review_required',
      },
    };
  });
  return { rows, entries };
}

function makeProductDocument(entry) {
  const { item, category, pricing } = entry;
  return {
    name: item.title,
    description: item.description || item.title,
    shortDescription: item.description.slice(0, 300),
    longDescription: cleanHtml(item.description),
    currency: 'PKR',
    price: pricing.draftSellingPrice || 0,
    category: category.parent,
    subcategory: category.child,
    categoryPath: [category.parent, category.child],
    productType: String(category.child).toLowerCase().replace(/[^a-z0-9]+/g, '-'),
    tags: item.tags,
    slug: `${String(item.title).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 120)}-${String(item.handle).toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 24)}`,
    storeName: 'FASTXHORIZON',
    brand: 'FASTXHORIZON',
    image: item.images[0] || '',
    images: item.images,
    stock: 0,
    inventoryMode: 'SUPPLIER_BASED',
    availabilityStatus: 'AVAILABLE_TO_ORDER',
    supplierKey: entry.supplierKey,
    supplierName: SUPPLIER,
    supplierSKU: item.supplierSKU,
    sourceHandle: item.handle,
    sourceUrl: item.sourceUrl,
    sourcePrice: item.sourcePrice,
    draftSellingPrice: pricing.draftSellingPrice,
    targetProfit: pricing.targetProfit,
    pricingStatus: 'REVIEW_REQUIRED',
    status: 'draft',
    rating: 0,
    numReviews: 0,
  };
}

module.exports = { SUPPLIER, safeFilename, buildEntries, makeProductDocument };
