const { buildEntries, makeProductDocument } = require('../services/catalogImport');

describe('admin catalog import workflow', () => {
  test('previews Shopify rows and groups gallery images without creating records', () => {
    const csv = [
      'Handle,Title,Body (HTML),Variant Price,Image Src',
      'blue-earbuds,Blue Earbuds,"<p>Wireless</p>",1200,https://example.com/one.jpg',
      'blue-earbuds,,,,https://example.com/two.jpg',
    ].join('\n');
    const result = buildEntries(csv);
    expect(result.rows).toHaveLength(2);
    expect(result.entries).toHaveLength(1);
    expect(result.entries[0].item.images).toHaveLength(2);
    expect(result.entries[0].preview.pricingStatus).toBe('REVIEW_REQUIRED');
  });

  test('creates draft supplier documents with private pricing metadata and no publication', () => {
    const csv = 'Handle,Title,Variant Price,Image Src\nitem,Item,1000,https://example.com/item.jpg';
    const document = makeProductDocument(buildEntries(csv).entries[0]);
    expect(document.supplierKey).toBe('YOURMART:item');
    expect(document.status).toBe('draft');
    expect(document.pricingStatus).toBe('REVIEW_REQUIRED');
    expect(document.sellingPrice).toBeUndefined();
    expect(document.price).toBeGreaterThan(0);
    expect(document.inventoryMode).toBe('SUPPLIER_BASED');
  });
});
