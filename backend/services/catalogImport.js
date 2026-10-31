const { groupProducts, parseCsv, draftPricing, categoryFor, cleanHtml } = require('../scripts/import-yourmart-csv');

const SUPPLIER = 'YOURMART';

function safeFilename(value) {
  return String(value || 'catalog.csv').replace(/[^a-zA-Z0-9._-]/g, '_').slice(0, 255) || 'catalog.csv';
}

function normalizeHeader(value) {
  return String(value || '').replace(/^\uFEFF/, '').trim().toLowerCase();
}

function numberOrNull(value) {
  const raw = String(value ?? '').trim();
  if (!raw) return null;
  const parsed = Number(raw.replace(/[^0-9.-]/g, ''));
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
}

function slug(value) {
  return String(value || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 150);
}

function splitList(value, separator = ',') {
  return [...new Set(String(value || '').split(separator).map((item) => item.trim()).filter(Boolean))];
}

function csvValue(row, names) {
  const keys = Object.keys(row);
  const match = keys.find((key) => names.includes(normalizeHeader(key)));
  return match ? String(row[match] || '').trim() : '';
}

function isWooCommerceRows(rows) {
  const headers = Object.keys(rows[0] || {}).map(normalizeHeader);
  return headers.includes('regular price') && headers.includes('in stock?') && headers.includes('images');
}

function mapWooItem(row, index) {
  const name = csvValue(row, ['name', 'title']) || `Imported product ${index + 1}`;
  const supplierSKU = csvValue(row, ['sku']);
  const key = supplierSKU || slug(name);
  const rawCategories = csvValue(row, ['categories', 'category']);
  const categoryPath = rawCategories.split(/\s*>\s*/).map((item) => item.trim()).filter(Boolean);
  const images = splitList(csvValue(row, ['images', 'image']), ',').filter((url) => /^https?:\/\//i.test(url));
  const rawDescription = csvValue(row, ['description', 'short description']);
  const shortDescription = csvValue(row, ['short description']) || cleanHtml(rawDescription).slice(0, 300);
  return {
    handle: key,
    title: name,
    description: rawDescription,
    descriptionHtml: rawDescription,
    shortDescription,
    images,
    sourcePrice: numberOrNull(csvValue(row, ['regular price', 'price'])),
    supplierSKU,
    sourceUrl: csvValue(row, ['external url', 'product url', 'url']),
    sourceCategories: rawCategories,
    sourceCategoryPath: categoryPath,
    sourceSupplier: csvValue(row, ['supplier']) || SUPPLIER,
    sourceParent: csvValue(row, ['parent']),
    sourceType: csvValue(row, ['type']),
    sourcePublished: csvValue(row, ['published']),
    sourceInStock: /^(1|yes|true|instock|in stock)$/i.test(csvValue(row, ['in stock?', 'in stock', 'stock'])),
    tags: splitList(csvValue(row, ['tags', 'product tags']), ','),
  };
}

function wooCategoryMatch(item, categories = []) {
  const [parentValue, childValue] = item.sourceCategoryPath;
  const normalize = (value) => slug(value);
  const parent = categories.find((category) => !category.parentSlug && [category.slug, category.name].some((value) => normalize(value) === normalize(parentValue)));
  const child = parent && childValue ? categories.find((category) => category.parentSlug === parent.slug && [category.slug, category.name].some((value) => normalize(value) === normalize(childValue))) : null;
  return { parent: parent?.name || '', child: child?.name || '', matched: Boolean(parent) && (!childValue || Boolean(child)), source: item.sourceCategories };
}

function wooPreview(entry, existingKeys = new Set(), existingSkus = new Set(), duplicateSkus = new Set()) {
  const { item, category } = entry;
  const duplicate = existingKeys.has(entry.supplierKey) || (item.supplierSKU && existingSkus.has(item.supplierSKU.toUpperCase())) || duplicateSkus.has(item.supplierSKU?.toUpperCase());
  const valid = item.title && item.images.length && item.sourcePrice !== null && category.matched && item.supplierSKU;
  return {
    supplierKey: entry.supplierKey,
    name: item.title,
    sku: item.supplierSKU,
    supplierSKU: item.supplierSKU,
    slug: `${slug(item.title)}-${slug(item.handle).slice(0, 24)}`,
    shortDescription: item.shortDescription,
    descriptionHtml: item.descriptionHtml,
    category: category.parent,
    subcategory: category.child,
    sourceCategory: category.source,
    sourcePrice: item.sourcePrice,
    sellingPrice: item.sellingPrice ?? null,
    draftSellingPrice: null,
    stockAvailable: item.sourceInStock,
    image: item.images[0] || null,
    images: item.images,
    imageCount: item.images.length,
    tags: item.tags,
    supplier: item.sourceSupplier,
    parent: item.sourceParent,
    pricingStatus: 'REVIEW_REQUIRED',
    duplicate,
    validation: valid ? (duplicate ? 'duplicate' : 'valid') : (category.matched ? 'review_required' : 'unmatched_category'),
  };
}

function buildWooCommerceEntries(rows, categories = []) {
  const groups = new Map();
  rows.forEach((row, index) => {
    const item = mapWooItem(row, index);
    if (!groups.has(item.handle)) groups.set(item.handle, item);
    else {
      const current = groups.get(item.handle);
      current.images = [...new Set([...current.images, ...item.images])];
      current.description = current.description || item.description;
      current.descriptionHtml = current.descriptionHtml || item.descriptionHtml;
    }
  });
  return [...groups.values()].map((item) => {
    const category = wooCategoryMatch(item, categories);
    const entry = { format: 'woocommerce', item, category, supplierKey: `${SUPPLIER}:WOOCOMMERCE:${item.handle}` };
    entry.preview = wooPreview(entry);
    return entry;
  });
}

function buildEntries(csvText, options = {}) {
  if (typeof csvText !== 'string' || Buffer.byteLength(csvText, 'utf8') > 10 * 1024 * 1024) {
    throw new Error('CSV must be valid text and no larger than 10 MB.');
  }
  const rows = parseCsv(csvText);
  if (isWooCommerceRows(rows)) return { rows, entries: buildWooCommerceEntries(rows, options.categories || []), format: 'woocommerce' };
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
  if (entry.format === 'woocommerce') {
    const { item, category } = entry;
    const sellingPrice = Number(item.sellingPrice);
    return {
      name: item.title,
      description: cleanHtml(item.description || item.title),
      shortDescription: cleanHtml(item.shortDescription || item.description || '').slice(0, 300),
      longDescription: cleanHtml(item.description || item.title),
      currency: 'PKR',
      price: Number.isFinite(sellingPrice) && sellingPrice > 0 ? sellingPrice : 0,
      category: category.parent,
      subcategory: category.child,
      categoryPath: category.child ? [category.parent, category.child] : [category.parent],
      productType: 'PHYSICAL',
      tags: item.tags,
      slug: `${slug(item.title)}-${slug(item.handle).slice(0, 24)}`,
      storeName: 'FASTXHORIZON',
      brand: '',
      image: item.images[0] || '',
      images: item.images,
      stock: 0,
      inventoryMode: 'SUPPLIER_BASED',
      availabilityStatus: 'AVAILABLE_TO_ORDER',
      supplierKey: entry.supplierKey,
      supplierName: item.sourceSupplier || SUPPLIER,
      supplierSKU: item.supplierSKU,
      sourceHandle: item.handle,
      sourceUrl: item.sourceUrl,
      sourcePrice: item.sourcePrice,
      sourceParent: item.sourceParent,
      sourceFormat: 'woocommerce',
      sourcePublished: item.sourcePublished,
      sourceInStock: item.sourceInStock,
      draftSellingPrice: null,
      sellingPrice: Number.isFinite(sellingPrice) && sellingPrice > 0 ? sellingPrice : null,
      confirmedSupplierCost: null,
      pricingStatus: 'REVIEW_REQUIRED',
      status: 'draft',
      rating: 0,
      numReviews: 0,
    };
  }
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

module.exports = { SUPPLIER, safeFilename, buildEntries, buildWooCommerceEntries, wooCategoryMatch, wooPreview, makeProductDocument };
