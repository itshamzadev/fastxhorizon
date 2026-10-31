require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('../models/product');
const { ingestSupplierImages } = require('../services/supplierImageIngestion');

const uri = process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/fastxhorizon_local';

async function main() {
  const target = new URL(uri);
  if (!['127.0.0.1', 'localhost', '::1'].includes(target.hostname) || target.pathname !== '/fastxhorizon_local') {
    throw new Error('Refusing to publish preview products outside local fastxhorizon_local MongoDB.');
  }
  await mongoose.connect(uri, { serverSelectionTimeoutMS: 5000 });
  const products = await Product.find({ supplierName: 'ARTO Mall', status: 'draft' }).select('+supplierImageSources name image images').lean();
  const report = [];
  for (const product of products) {
    try {
      const urls = (product.supplierImageSources || []).map(item => item.url).filter(Boolean);
      const hasInternalImage = String(product.image || '').startsWith('/media/products/') && Array.isArray(product.images) && product.images.length > 0 && product.images.every(item => String(item).startsWith('/media/products/'));
      const media = hasInternalImage ? product.images.map((mediaPath, index) => ({ mediaPath, url: urls[index] || urls[0] || '' })) : await ingestSupplierImages(urls, { supplier: 'artomall' });
      const update = {
        status: 'active',
        pricingStatus: 'MANUAL_OVERRIDE',
        updatedAt: new Date(),
      };
      if (!hasInternalImage) {
        update.image = media[0].mediaPath;
        update.images = media.map(item => item.mediaPath);
        update.supplierImageSources = media;
      }
      await Product.updateOne({ _id: product._id }, {
        $set: update,
      });
      report.push({ name: product.name, status: 'published', image: media[0].mediaPath, reusedInternalImage: hasInternalImage });
    } catch (error) {
      report.push({ name: product.name, status: 'failed', reason: error.message });
    }
  }
  console.log(JSON.stringify({ target: `${target.hostname}${target.pathname}`, total: products.length, published: report.filter(item => item.status === 'published').length, failed: report.filter(item => item.status === 'failed').length, report }, null, 2));
  await mongoose.disconnect();
}

main().catch(async error => {
  console.error(error.stack || error.message);
  await mongoose.disconnect().catch(() => {});
  process.exitCode = 1;
});
