const express = require('express');
const { ensureVariant } = require('../services/mediaVariants');

const router = express.Router();

router.get('/:filename', async (req, res) => {
  try {
    const variant = await ensureVariant(req.params.filename, req.query.w, req.query.format);
    if (!variant) return res.status(404).json({ message: 'Product image not found.' });
    res.set({
      'Cache-Control': 'public, max-age=31536000, immutable',
      'X-Content-Type-Options': 'nosniff',
      Vary: 'Accept',
    });
    return res.type(variant.format).sendFile(variant.target);
  } catch (error) {
    console.error('Product image variant failed:', error.message);
    return res.status(500).json({ message: 'Unable to optimize product image.' });
  }
});

module.exports = router;
