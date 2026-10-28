const { parseCsv, cleanHtml, groupProducts, draftPricing } = require('../scripts/import-yourmart-csv');

describe('YouMart CSV importer', () => {
  test('parses quoted multiline Shopify descriptions and groups image rows', () => {
    const csv = 'Handle,Title,Body (HTML),Variant SKU,Variant Price,Image Src\n' +
      'lamp,Desk Lamp,"<p>Line one</p>\nLine two",SKU-1,500,https://example.test/1.jpg\n' +
      'lamp,,,,,https://example.test/2.jpg\n';
    const groups = groupProducts(parseCsv(csv));
    expect(groups).toHaveLength(1);
    expect(groups[0].images).toEqual(['https://example.test/1.jpg', 'https://example.test/2.jpg']);
    expect(cleanHtml(groups[0].description)).toContain('Line one');
    expect(groups[0].sourcePrice).toBe(500);
  });

  test('creates reviewable draft pricing without claiming confirmed supplier cost', () => {
    const pricing = draftPricing(1290);
    expect(pricing.draftSellingPrice).toBeGreaterThan(1290);
    expect(pricing.targetProfit).toBeGreaterThan(0);
  });
});
