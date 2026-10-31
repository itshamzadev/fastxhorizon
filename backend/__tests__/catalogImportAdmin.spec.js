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

  test('parses WooCommerce CSV fields, multiline HTML and comma-separated images', () => {
    const csv = [
      'SKU,Type,Name,Published,Short description,Description,Regular price,In stock?,Images,Categories,Supplier,Parent',
      'WC-001,simple,Demo headphones,1,"Short, quoted text","<p>Line one</p>\n<p>Line two, with comma</p>",2999,1,"https://example.com/one.jpg,https://example.com/two.jpg","Electronics > Headphones",,',
    ].join('\n');
    const result = buildEntries(csv, { format: 'woocommerce', categories: [
      { name: 'Electronics', slug: 'electronics', parentSlug: null },
      { name: 'Headphones', slug: 'headphones', parentSlug: 'electronics' },
    ] });
    expect(result.format).toBe('woocommerce');
    expect(result.entries).toHaveLength(1);
    expect(result.entries[0].item.images).toHaveLength(2);
    expect(result.entries[0].item.sourcePrice).toBe(2999);
    expect(result.entries[0].category).toMatchObject({ parent: 'Electronics', child: 'Headphones', matched: true });
    expect(result.entries[0].preview.validation).toBe('valid');
  });

  test('keeps blank WooCommerce prices invalid instead of converting them to zero', () => {
    const result = buildEntries('SKU,Name,Regular price,In stock?,Images,Categories\nWC-002,No price,,0,https://example.com/item.jpg,Electronics', { format: 'woocommerce', categories: [{ name: 'Electronics', slug: 'electronics', parentSlug: null }] });
    expect(result.entries[0].item.sourcePrice).toBeNull();
    expect(result.entries[0].preview.validation).toBe('review_required');
  });
});
