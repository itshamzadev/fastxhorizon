const mongoose = require('../backend/node_modules/mongoose');
const bcrypt = require('../backend/node_modules/bcryptjs');
const Product = require('../backend/models/product');
const User = require('../backend/models/user');
const Admin = require('../backend/models/admin');
const PaymentConfiguration = require('../backend/models/paymentConfiguration');

const URI = 'mongodb://127.0.0.1:27017/fastxhorizon_e2e';
const email = 'phase7-customer@example.invalid';
const adminEmail = 'phase7-admin@example.invalid';
const password = 'Phase7-Test-Password-123!';

module.exports = async () => {
  if (!URI.endsWith('/fastxhorizon_e2e')) throw new Error('E2E setup refused unsafe database URI');
  await mongoose.connect(URI);
  await Promise.all([Product.deleteMany({ sourceFormat: 'PHASE7_E2E' }), User.deleteMany({ email }), Admin.deleteMany({ email: adminEmail }), PaymentConfiguration.deleteMany({ key: 'v1' })]);
  const hash = await bcrypt.hash(password, 10);
  await User.create({ name: 'Phase 7 Customer', email, password: hash });
  await Admin.create({ name: 'Phase 7 Admin', email: adminEmail, passwordHash: hash, role: 'super_admin', permissions: ['manage_inventory', 'reviews.manage', 'payments.read'], status: 'active' });
  await PaymentConfiguration.create({ key: 'v1', ordersEnabled: true, manualEnabled: true });
  await Product.create([
    { name: 'Phase 7 Discount Shirt', description: 'E2E physical clothing fixture', price: 1500, compareAtPrice: 2000, category: 'Clothing', brand: 'Phase 7 Brand', fabricMaterial: 'Cotton', image: '/media/products/phase7.png', images: ['/media/products/phase7.png'], sku: 'PHASE7-DISCOUNT', stock: 1, inventoryMode: 'NUMERIC', availabilityStatus: 'IN_STOCK', status: 'active', sourceFormat: 'PHASE7_E2E', allowArrangeOnRequest: true },
    { name: 'Phase 7 Arrange Shirt', description: 'E2E sold-out arrangement fixture', price: 5000, compareAtPrice: 6000, category: 'Clothing', brand: 'Phase 7 Brand', fabricMaterial: 'Khaddar', image: '/media/products/phase7.png', images: ['/media/products/phase7.png'], sku: 'PHASE7-ARRANGE', stock: 0, inventoryMode: 'NUMERIC', availabilityStatus: 'OUT_OF_STOCK', status: 'active', sourceFormat: 'PHASE7_E2E', allowArrangeOnRequest: true },
    { name: 'Phase 7 Supplier Jewellery', description: 'E2E supplier fixture', price: 2200, category: 'Jewellery', brand: 'Phase 7 Brand', image: '/media/products/phase7.png', sku: 'PHASE7-SUPPLIER', stock: 0, inventoryMode: 'SUPPLIER_BASED', availabilityStatus: 'AVAILABLE_TO_ORDER', status: 'active', sourceFormat: 'PHASE7_E2E' },
  ]);
  await mongoose.disconnect();
};

module.exports.credentials = { email, adminEmail, password };
