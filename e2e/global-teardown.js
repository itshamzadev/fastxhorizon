const mongoose = require('../backend/node_modules/mongoose');
module.exports = async () => {
  const uri = 'mongodb://127.0.0.1:27017/fastxhorizon_e2e';
  if (!uri.endsWith('/fastxhorizon_e2e')) throw new Error('E2E teardown refused unsafe database URI');
  await mongoose.connect(uri);
  await mongoose.connection.db.dropDatabase();
  await mongoose.disconnect();
};
