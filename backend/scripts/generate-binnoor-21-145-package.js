/*
 * Build the file-only BinNoor 21-145 migration package from a Stock Manager
 * export. This script never connects to MongoDB or production.
 */
const fs = require('fs');
const fsp = fs.promises;
const path = require('path');
const crypto = require('crypto');

const ROOT = path.resolve(__dirname, '../..');
const SOURCE = path.resolve(process.env.BINNOOR_21_145_SOURCE || path.join(ROOT, '21 to 145'));
const OUTPUT = path.resolve(process.env.BINNOOR_21_145_OUTPUT || path.join(ROOT, 'others', 'binnoor-21-145-production-migration'));
const FIRST_PACKAGE = path.join(ROOT, 'others', 'binnoor-production-migration', 'products.json');
const json = file => JSON.parse(fs.readFileSync(file, 'utf8'));
const writeJson = (file, value) => fs.writeFileSync(file, `${JSON.stringify(value, null, 2)}\n`, 'utf8');
const sha256 = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const pad = n => String(n).padStart(4, '0');
const safeName = value => String(value || '').trim();
const mediaPath = ref => String(ref || '').replace(/\\/g, '/').replace(/^images\//, '');
const publicMediaPath = filename => `/media/products/${filename}`;

function assert(condition, message) { if (!condition) throw new Error(message); }
function money(value, label) { const n = Number(value); assert(Number.isFinite(n) && n >= 0, `${label} is invalid.`); return n; }

function load() {
  const manifest = json(path.join(SOURCE, 'manifest.json'));
  const payload = json(path.join(SOURCE, 'products.json'));
  const brands = json(path.join(SOURCE, 'brands.json'));
  const categories = json(path.join(SOURCE, 'categories.json'));
  const fabricTypes = json(path.join(SOURCE, 'fabric-types.json'));
  assert(manifest.source === 'FXH_STOCK_MANAGER' && Number(manifest.formatVersion) === 3, 'Unsupported Stock Manager export format.');
  assert(Array.isArray(payload.products) && Array.isArray(payload.variants), 'products.json must contain products and variants arrays.');
  return { manifest, payload, brands, categories, fabricTypes };
}

function main() {
  const { manifest, payload, brands, categories, fabricTypes } = load();
  const existing = fs.existsSync(FIRST_PACKAGE) ? json(FIRST_PACKAGE).products || [] : [];
  const existingKeys = new Set(existing.map(item => item.supplierKey).filter(Boolean));
  const existingSources = new Set(existing.map(item => item.sourceProductId).filter(Boolean));
  const errors = [];
  const warnings = [];
  const products = [];
  const seenIds = new Set();
  const seenKeys = new Set();
  const seenCatalogNumbers = new Set();
  const variantsByProduct = new Map();
  const variantIds = new Set();
  const sourceProductIds = new Set(payload.products.map(item => String(item.importId || item.id || '')));

  for (const variant of payload.variants) {
    const variantId = String(variant.sourceVariantId || variant.importId || variant.id || '');
    const productId = String(variant.productImportId || variant.product_id || '');
    if (!variantId || variantIds.has(variantId)) errors.push(`Duplicate/missing source variant ID: ${variantId || '(missing)'}`);
    variantIds.add(variantId);
    if (!sourceProductIds.has(productId)) errors.push(`Variant ${variantId} references missing product ${productId}.`);
    const list = variantsByProduct.get(productId) || [];
    list.push(variant);
    variantsByProduct.set(productId, list);
  }

  const usedBrands = new Map();
  const usedFabric = new Map();
  const usedImages = new Map();
  let totalQuantity = 0;
  let excludedExisting = 0;
  const sorted = [...payload.products].sort((a, b) => Number(a.catalogNumber || 0) - Number(b.catalogNumber || 0));
  for (const source of sorted) {
    const sourceId = String(source.sourceProductId || source.importId || source.id || '');
    const key = String(source.supplierKey || '');
    const number = Number(source.catalogNumber);
    if (!sourceId || seenIds.has(sourceId)) errors.push(`Duplicate/missing source product ID: ${sourceId || '(missing)'}`);
    if (!key || seenKeys.has(key)) errors.push(`Duplicate/missing supplierKey: ${key || '(missing)'}`);
    if (!Number.isInteger(number) || seenCatalogNumbers.has(number)) errors.push(`Duplicate/invalid catalog number: ${source.catalogNumber}`);
    seenIds.add(sourceId); seenKeys.add(key); seenCatalogNumbers.add(number);
    if (existingKeys.has(key) || existingSources.has(sourceId)) { excludedExisting += 1; continue; }
    if (number <= 20) { warnings.push(`Excluded catalog number <= 20: ${number}`); continue; }
    if (source.categorySlug !== 'clothing' || String(source.categoryName || '').toLowerCase() !== 'clothing') errors.push(`${sourceId}: category is not Clothing.`);
    if (!safeName(source.name) || !safeName(source.description)) errors.push(`${sourceId}: name or description is missing.`);
    const selling = money(source.sellingPrice, `${sourceId}.sellingPrice`);
    const original = money(source.originalPrice, `${sourceId}.originalPrice`);
    if (selling > original) errors.push(`${sourceId}: selling price exceeds original price.`);
    const expectedDiscount = original > 0 ? Math.round(((original - selling) / original) * 10000) / 100 : 0;
    if (Number(source.discountPercentage || 0) !== expectedDiscount) errors.push(`${sourceId}: discount percentage is inconsistent.`);
    const sourceVariants = variantsByProduct.get(String(source.importId || source.id || '')) || [];
    const quantity = Number(source.quantity);
    if (!Number.isFinite(quantity) || quantity < 0) errors.push(`${sourceId}: invalid quantity.`);
    totalQuantity += Number.isFinite(quantity) ? quantity : 0;
    const refs = new Set([...(source.images || []), ...sourceVariants.flatMap(item => item.images || [])]);
    if (!refs.size) errors.push(`${sourceId}: no product images.`);
    const targetNumber = 230 + products.length + 1;
    const sku = `FXH-FXH-BN-${pad(targetNumber)}`;
    const images = Array.from(refs).map(mediaPath);
    for (const filename of images) {
      const file = path.join(SOURCE, 'images', filename);
      if (!fs.existsSync(file)) errors.push(`${sourceId}: missing image ${filename}.`);
      else if (!usedImages.has(filename)) usedImages.set(filename, { filename, sourceProducts: [] });
      if (usedImages.has(filename)) usedImages.get(filename).sourceProducts.push(sourceId);
    }
    const brand = safeName(source.brandName || source.brand);
    const fabric = safeName(source.fabricMaterial || source.fabric);
    if (brand) usedBrands.set(brand, brands.find(item => item.name === brand) || { name: brand, active: 1 });
    if (fabric) usedFabric.set(fabric, fabricTypes.find(item => item.name === fabric) || { name: fabric, active: 1 });
    const targetVariants = sourceVariants.map((variant, index) => {
      const variantImages = (variant.images || []).map(mediaPath);
      const variantPrice = money(variant.price ?? selling, `${sourceId}.variant.price`);
      const compareAt = variant.compareAtPrice === null || variant.compareAtPrice === undefined ? null : money(variant.compareAtPrice, `${sourceId}.variant.compareAtPrice`);
      const variantSku = sourceVariants.length === 1 ? sku : `${sku}-${String(index + 1).padStart(2, '0')}`;
      return { sourceVariantId: String(variant.sourceVariantId || variant.importId || variant.id), productImportId: sourceId, sku: variantSku, color: variant.color ?? null, size: variant.size ?? null, price: variantPrice, compareAtPrice: compareAt, discountAmount: compareAt !== null ? Math.max(0, compareAt - variantPrice) : 0, discountPercentage: compareAt && compareAt > 0 ? Math.round(((compareAt - variantPrice) / compareAt) * 10000) / 100 : 0, quantity: Number(variant.available_quantity || 0), images: variantImages.map(publicMediaPath) };
    });
    products.push({ sourceProductId: sourceId, supplierKey: key, catalogNumber: number, sku, name: source.name, description: source.description, fabricMaterial: fabric, specifications: source.specifications || [], brandId: source.brandId || null, brandName: brand, brand, categoryId: source.categoryId || null, categorySlug: source.categorySlug, categoryName: source.categoryName, category: source.categoryName, currency: 'PKR', sellingPrice: selling, originalPrice: original, discountAmount: Number(source.discountAmount || Math.max(0, original - selling)), discountPercentage: expectedDiscount, quantity, inventoryMode: source.inventoryMode || 'NUMERIC', availabilityStatus: source.availabilityStatus || (quantity > 0 ? 'in_stock' : 'out_of_stock'), status: 'draft', slug: source.slug, images: images.map(publicMediaPath), sourceImages: images.map(filename => `media/${filename}`), variants: targetVariants });
  }
  for (const [filename, item] of usedImages) { item.sourceProducts = Array.from(new Set(item.sourceProducts)); const file = path.join(SOURCE, 'images', filename); const bytes = fs.readFileSync(file); item.bytes = bytes.length; item.sha256 = sha256(bytes); item.mimeType = filename.toLowerCase().endsWith('.webp') ? 'image/webp' : filename.toLowerCase().endsWith('.jpg') || filename.toLowerCase().endsWith('.jpeg') ? 'image/jpeg' : 'image/png'; }
  if (products.length !== sorted.length - excludedExisting) warnings.push(`Source contains ${sorted.length} products; ${excludedExisting} matched the original package and ${products.length} remain.`);
  assert(!errors.length, `Source validation failed:\n${errors.join('\n')}`);
  assert(products.length > 0, 'No new products remain after exclusion.');
  const variants = products.flatMap(item => item.variants);
  const outputProducts = products.map(({ sourceImages, variants: ignored, ...item }) => item);
  const manifestOut = { formatVersion: 4, source: 'FXH_STOCK_MANAGER', packageType: 'BINNOOR_FAB_21_145_PRODUCTION_MIGRATION', generatedAt: new Date().toISOString(), sourceFolder: '21 to 145', sourceCatalogNumberRange: { from: Math.min(...products.map(item => item.catalogNumber)), to: Math.max(...products.map(item => item.catalogNumber)) }, products: products.length, variants: variants.length, mediaFiles: usedImages.size, totalPhysicalQuantity: totalQuantity, excludedAlreadyImported: excludedExisting, expectedProductionSkuRange: { from: products[0].sku, to: products[products.length - 1].sku }, currency: 'PKR', includesInternalCosts: false, publicIdentifiers: 'generated-from-stable-local-record-ids', notes: warnings };
  fs.rmSync(OUTPUT, { recursive: true, force: true }); fs.mkdirSync(path.join(OUTPUT, 'media'), { recursive: true });
  for (const item of usedImages.values()) fs.copyFileSync(path.join(SOURCE, 'images', item.filename), path.join(OUTPUT, 'media', item.filename));
  writeJson(path.join(OUTPUT, 'products.json'), { exportedAt: manifestOut.generatedAt, formatVersion: 4, source: 'FXH_STOCK_MANAGER', currency: 'PKR', products: outputProducts, variants });
  writeJson(path.join(OUTPUT, 'categories.json'), categories.filter(item => String(item.slug).toLowerCase() === 'clothing'));
  writeJson(path.join(OUTPUT, 'brands.json'), Array.from(usedBrands.values()));
  writeJson(path.join(OUTPUT, 'fabric-types.json'), Array.from(usedFabric.values()));
  writeJson(path.join(OUTPUT, 'manifest.json'), manifestOut);
  writeJson(path.join(OUTPUT, 'media-manifest.json'), Array.from(usedImages.values()).map(item => ({ filename: item.filename, sha256: item.sha256, bytes: item.bytes, mimeType: item.mimeType, destination: `/app/media/products/${item.filename}`, publicUrl: `https://api.fastxhorizon.com/media/products/${item.filename}`, sourceProducts: item.sourceProducts })));
  fs.writeFileSync(path.join(OUTPUT, 'README.md'), readme(manifestOut), 'utf8');
  const cleanScript = value => value.replace(/\n\+/g, '\n').replace(/require\('dotenv'\)\.config\(\);/g, "try{require('dotenv').config();}catch(e){}").replace(/mongoose=require\('mongoose'\)/g, "mongoose=require(path.join(process.env.BACKEND_ROOT||process.cwd(),'node_modules','mongoose'))");
  fs.writeFileSync(path.join(OUTPUT, 'import-production.js'), cleanScript(importer()), 'utf8');
  fs.writeFileSync(path.join(OUTPUT, 'verify-production.js'), cleanScript(verifier()), 'utf8');
  fs.writeFileSync(path.join(OUTPUT, 'publish-production.js'), cleanScript(publisher()), 'utf8');
  console.log(JSON.stringify({ output: OUTPUT, manifest: manifestOut, validation: { errors: errors.length, warnings: warnings.length, missingImages: 0, duplicateSourceIds: 0 }, images: usedImages.size }, null, 2));
}

function readme(m) { return `# BinNoor Fab Products 21–145 Production Migration\n\nGenerated from the supplied \`21 to 145\` Stock Manager export. This package contains ${m.products} new product records, ${m.variants} variants and ${m.mediaFiles} verified image files. The source contained ${m.totalPhysicalQuantity} physical units.\n\n## Important validation result\n\nThe source manifest actually contains catalog numbers ${m.sourceCatalogNumberRange.from}–${m.sourceCatalogNumberRange.to}; it contains ${m.products} new product records, not an assumed 125. Existing products matching the original 20-product package were excluded. Product names and brands are preserved exactly from the source; the batch is not forced to a single customer-facing brand.\n\nAll imports are draft, use PKR, omit internal buying costs and supplier financial data, and use stable \`supplierKey\`/source IDs. Images must be copied to the persistent \`/app/media/products\` directory.\n\n## Required environment\n\`PRODUCTION_MONGO_URI\` must target exactly \`fastxhorizon_production\`. Never put it in this package or Git. Set \`BACKEND_ROOT=/app\` and \`MEDIA_STORAGE_ROOT=/app/media\` inside the backend environment.\n\n## Commands\n\nFrom this package directory inside the backend container:\n\n\`node import-production.js --dry-run\`\n\`node import-production.js --apply\` (requires verified backup/persistence confirmations)\n\`node verify-production.js\`\n\`node publish-production.js\` (separate explicit approval)\n\`node verify-production.js --public\`\n\nApply requires \`PRODUCTION_IMPORT_CONFIRM=YES\`, \`PRODUCTION_BACKUP_VERIFIED=YES\`, \`PRODUCTION_MEDIA_PERSISTENT=YES\`, \`PRODUCTION_BACKUP_ARCHIVE=/absolute/path/to/verified.archive.gz\` and \`PRODUCTION_NAME_INDEX_MIGRATION_APPROVED=YES\` only as an acknowledgement; the script never drops or changes indexes.\n\n## Safety\n\nDry-run first. Back up the production products/categories/SKU counter. The importer rejects loopback/local databases, checks every SKU and image hash, inserts only missing stable source keys, never deletes products, never overwrites conflicting media, and leaves new products as draft. If production has a different global SKU head than the package range, stop and regenerate/review the package.\n`; }

function importer() { return `require('dotenv').config();\n+const fs=require('fs'),path=require('path'),crypto=require('crypto'),mongoose=require('mongoose');\n+const ROOT=process.env.BACKEND_ROOT||process.cwd(), Product=require(path.join(ROOT,'models','product')), Category=require(path.join(ROOT,'models','category'));\n+const MODE=process.argv[2]||'--dry-run'; if(!['--dry-run','--apply'].includes(MODE)) throw Error('Use --dry-run or --apply.');\n+const URI=process.env.PRODUCTION_MONGO_URI||'', MEDIA=process.env.MEDIA_STORAGE_ROOT||'', DIR=__dirname, pack=JSON.parse(fs.readFileSync(path.join(DIR,'products.json'))), manifest=JSON.parse(fs.readFileSync(path.join(DIR,'manifest.json'))), mm=JSON.parse(fs.readFileSync(path.join(DIR,'media-manifest.json'))); const hash=b=>crypto.createHash('sha256').update(b).digest('hex');\n+function target(){if(!URI)throw Error('PRODUCTION_MONGO_URI is required.');const u=new URL(URI);if(u.pathname.slice(1)!=='fastxhorizon_production'||['localhost','127.0.0.1','::1'].includes(u.hostname))throw Error('URI must target a non-loopback fastxhorizon_production database.');return u;} function gate(){for(const k of ['PRODUCTION_IMPORT_CONFIRM','PRODUCTION_BACKUP_VERIFIED','PRODUCTION_MEDIA_PERSISTENT'])if(process.env[k]!=='YES')throw Error(k+'=YES is required for apply.');if(!process.env.PRODUCTION_BACKUP_ARCHIVE||!fs.existsSync(path.resolve(process.env.PRODUCTION_BACKUP_ARCHIVE)))throw Error('Verified PRODUCTION_BACKUP_ARCHIVE is required.');if(!MEDIA||!path.isAbsolute(MEDIA))throw Error('MEDIA_STORAGE_ROOT must be an absolute persistent path.');}\n+async function main(){target();if(MODE==='--apply')gate();await mongoose.connect(URI,{serverSelectionTimeoutMS:10000});const db=mongoose.connection.db,all=await Product.find({}).select('sku supplierKey slug name status').lean(),idx=await db.collection('products').indexes(),nameIdx=idx.find(x=>x.name==='name_1'&&x.unique),byKey=new Map(all.filter(x=>x.supplierKey).map(x=>[x.supplierKey,x])),bySku=new Map(all.filter(x=>x.sku).map(x=>[x.sku,x])),pending=pack.products.filter(x=>!byKey.has(x.supplierKey)),highest=Math.max(0,...all.map(x=>{const m=String(x.sku||'').match(/^FXH-FXH-[A-Z]+-([0-9]{4})$/);return m?Number(m[1]):0})),blockers=[];for(const x of pack.products){if(bySku.has(x.sku)&&bySku.get(x.sku).supplierKey!==x.supplierKey)blockers.push('SKU collision '+x.sku);if(byKey.has(x.supplierKey)&&byKey.get(x.supplierKey).sku!==x.sku)blockers.push('Stable key has different SKU '+x.supplierKey);}if(nameIdx&&pack.products.some((x,i)=>pack.products.some((y,j)=>i<j&&x.name===y.name)))blockers.push('Unique name_1 index conflicts with duplicate source names; change it through a separately reviewed DBA migration.');if(pending.length&&Number(pending[0].sku.slice(-4))!==highest+1)blockers.push('Package begins at '+pending[0].sku+' but production next global sequence is '+(highest+1)+'.');const report={mode:MODE.slice(2),database:db.databaseName,before:{products:all.length,highestSku:highest},package:{products:pack.products.length,variants:pack.variants.length,images:mm.length},alreadyImported:pack.products.length-pending.length,pending:pending.map(x=>x.sku),indexes:idx.map(x=>({name:x.name,unique:Boolean(x.unique)})),blockers};for(const item of mm){const f=path.join(DIR,'media',item.filename);if(!fs.existsSync(f)||hash(fs.readFileSync(f))!==item.sha256)report.blockers.push('Package media checksum failed '+item.filename);if(MODE==='--apply'){const d=path.join(MEDIA,'products',item.filename);if(fs.existsSync(d)&&hash(fs.readFileSync(d))!==item.sha256)report.blockers.push('Production media collision '+item.filename);}}if(MODE==='--dry-run'||MODE==='--verify'){console.log(JSON.stringify(report,null,2));await mongoose.disconnect();if(report.blockers.length)process.exitCode=2;return;}if(report.blockers.length)throw Error(report.blockers.join('\\n'));await fs.promises.mkdir(path.join(MEDIA,'products'),{recursive:true});for(const item of mm){const src=path.join(DIR,'media',item.filename),dst=path.join(MEDIA,'products',item.filename);if(!fs.existsSync(dst))await fs.promises.copyFile(src,dst);}const category=await Category.findOne({slug:'clothing',parentSlug:null});if(!category)throw Error('Clothing category is missing; create/review it separately.');const variantsBy=new Map(pack.variants.map(v=>[v.productImportId,[]]));for(const v of pack.variants)(variantsBy.get(v.productImportId)||[]).push(v);const inserted=[];for(const x of pending){const vars=(variantsBy.get(x.sourceProductId)||[]).map(v=>({key:[v.color,v.size].filter(Boolean).join('-')||v.sku,options:{...(v.color?{color:v.color}:{}),...(v.size?{size:v.size}:{})},sku:v.sku,price:v.price,compareAtPrice:v.compareAtPrice,images:v.images.map(p=>p),inventoryMode:'NUMERIC',stock:v.quantity,availabilityStatus:v.quantity>0?'IN_STOCK':'OUT_OF_STOCK',enabled:true}));const doc=new Product({name:x.name,description:x.description,price:x.sellingPrice,currency:'PKR',category:'Clothing',subcategory:'',categoryPath:['Clothing'],productType:'PHYSICAL',longDescription:x.description,tags:[x.fabricMaterial,x.brand].filter(Boolean),slug:x.slug+'-'+x.sourceProductId,storeName:'FASTXHORIZON',image:x.images[0]||'',images:x.images,variantsEnabled:true,variants:vars,specifications:x.specifications||[],sku:x.sku,brand:x.brand,stock:x.quantity,inventoryMode:'NUMERIC',availabilityStatus:x.quantity>0?'IN_STOCK':'OUT_OF_STOCK',supplierKey:x.supplierKey,sourceHandle:x.sourceProductId,sourceFormat:'FXH_STOCK_MANAGER_V4',sourcePublished:'draft',sourceInStock:x.quantity>0,sellingPrice:x.sellingPrice,draftSellingPrice:x.sellingPrice,pricingStatus:'REVIEW_REQUIRED',status:'draft'});await doc.validate();const result=await db.collection('products').insertOne(doc.toObject());inserted.push(result.insertedId);}console.log(JSON.stringify({...report,after:{products:await Product.countDocuments(),inserted:inserted.length}},null,2));await mongoose.disconnect();}main().catch(e=>{console.error(e.message);mongoose.disconnect().catch(()=>{});process.exit(1)});\n`; }

function verifier() { return `require('dotenv').config();const fs=require('fs'),path=require('path'),crypto=require('crypto'),mongoose=require('mongoose');const ROOT=process.env.BACKEND_ROOT||process.cwd(),Product=require(path.join(ROOT,'models','product')),URI=process.env.PRODUCTION_MONGO_URI||'',DIR=__dirname,pack=JSON.parse(fs.readFileSync(path.join(DIR,'products.json'))),mm=JSON.parse(fs.readFileSync(path.join(DIR,'media-manifest.json'))),hash=b=>crypto.createHash('sha256').update(b).digest('hex');(async()=>{if(!URI)throw Error('PRODUCTION_MONGO_URI is required.');const u=new URL(URI);if(u.pathname.slice(1)!=='fastxhorizon_production'||['localhost','127.0.0.1','::1'].includes(u.hostname))throw Error('Refusing unexpected production URI.');await mongoose.connect(URI,{serverSelectionTimeoutMS:10000});const P=await Product.find({supplierKey:{$in:pack.products.map(x=>x.supplierKey)}}).lean(),byKey=new Map(P.map(x=>[x.supplierKey,x])),invalid=pack.products.filter(x=>{const y=byKey.get(x.supplierKey);return !y||y.sku!==x.sku||y.brand!==x.brand||y.category!=='Clothing'||!['draft','active'].includes(y.status)}),missingMedia=mm.filter(x=>!fs.existsSync(path.join(DIR,'media',x.filename))||hash(fs.readFileSync(path.join(DIR,'media',x.filename)))!==x.sha256);console.log(JSON.stringify({database:mongoose.connection.db.databaseName,expected:pack.products.length,found:P.length,missingOrInvalid:invalid.map(x=>x.sku),missingPackageMedia:missingMedia.map(x=>x.filename),statusCounts:P.reduce((a,x)=>(a[x.status]=(a[x.status]||0)+1,a),{}),publicReady:invalid.length===0},null,2));await mongoose.disconnect();})().catch(e=>{console.error(e.message);process.exit(1)});\n`; }
function publisher() { return `require('dotenv').config();const fs=require('fs'),path=require('path'),mongoose=require('mongoose');const Product=require(path.join(process.env.BACKEND_ROOT||process.cwd(),'models','product')),URI=process.env.PRODUCTION_MONGO_URI||'',confirm=process.env.BINNOOR_21_145_PUBLISH_CONFIRM==='YES',pack=JSON.parse(fs.readFileSync(path.join(__dirname,'products.json')));(async()=>{if(!confirm)throw Error('Set BINNOOR_21_145_PUBLISH_CONFIRM=YES for explicit approval.');const u=new URL(URI);if(u.pathname.slice(1)!=='fastxhorizon_production'||['localhost','127.0.0.1','::1'].includes(u.hostname))throw Error('Refusing unexpected production URI.');await mongoose.connect(URI,{serverSelectionTimeoutMS:10000});const result=await Product.updateMany({supplierKey:{$in:pack.products.map(x=>x.supplierKey)},sku:{$in:pack.products.map(x=>x.sku)},status:'draft'},{$set:{status:'active',sourcePublished:'active',updatedAt:new Date()}});console.log(JSON.stringify({matched:result.matchedCount,modified:result.modifiedCount,expected:pack.products.length},null,2));if(result.matchedCount!==pack.products.length)process.exitCode=2;await mongoose.disconnect();})().catch(e=>{console.error(e.message);process.exit(1)});\n`; }

main();
