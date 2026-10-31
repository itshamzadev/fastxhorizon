const { parseArtoText, makeProductDocument } = require('../services/catalogImport');

describe('ARTO Mall text importer', () => {
  const single = [
    'Golden Baroque Pearl Ring',
    '',
    'Price: Rs 1,110 (Cash on Delivery available)',
    '',
    'Elegant modern ring description.',
    '',
    'Features:',
    '- Baroque pearl',
    '- Adjustable open cuff',
    '',
    '[product image](https://i0.wp.com/artomall.com/image-171.png)',
  ].join('\n');

  test('parses price, description, features and image without inventing SKU', () => {
    const result = parseArtoText(single);
    expect(result.entries).toHaveLength(1);
    const item = result.entries[0].item;
    expect(item.title).toBe('Golden Baroque Pearl Ring');
    expect(item.sourcePrice).toBe(1110);
    expect(item.description).toContain('Elegant modern ring description.');
    expect(item.features).toEqual(['Baroque pearl', 'Adjustable open cuff']);
    expect(item.images).toEqual(['https://i0.wp.com/artomall.com/image-171.png']);
    expect(item.supplierSKU).toBe('');
  });

  test('splits bulk blocks using the documented separator and warns on missing fields', () => {
    const result = parseArtoText(`${single}\n\n---\n\nIncomplete product`);
    expect(result.entries).toHaveLength(2);
    expect(result.entries[1].warnings).toEqual(expect.arrayContaining(['Missing or invalid source price.', 'No product image URL found.']));
  });

  test('new ARTO documents remain draft and separate source/selling price', () => {
    const entry = parseArtoText(single).entries[0];
    entry.category = { parent: 'Jewellery', child: 'Rings' };
    entry.item.sellingPrice = 1800;
    const document = makeProductDocument(entry);
    expect(document.status).toBe('draft');
    expect(document.sourcePrice).toBe(1110);
    expect(document.sellingPrice).toBe(1800);
    expect(document.supplierName).toBe('ARTO Mall');
  });
});
