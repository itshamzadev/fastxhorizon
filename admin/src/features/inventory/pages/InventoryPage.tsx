import { useEffect, useState } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { apiClient } from '@/services/api/client'

type Product = { _id: string; name: string; sku?: string; brand?: string; stock?: number; inventoryMode?: string; availabilityStatus?: string; price?: number }
type Arrangement = { _id: string; requestNumber: string; status: string; quantity: number; productId?: { name?: string; price?: number }; advanceAmount?: number | null }

export function InventoryPage() {
  const [search, setSearch] = useState('')
  const [products, setProducts] = useState<Product[]>([])
  const [arrangements, setArrangements] = useState<Arrangement[]>([])
  const [selected, setSelected] = useState<Product | null>(null)
  const [quantity, setQuantity] = useState(1)
  const [sellingPrice, setSellingPrice] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [priceDrafts, setPriceDrafts] = useState<Record<string, string>>({})
  const load = () => {
    apiClient.get<{ data: Product[] }>('/admin/inventory/products', { query: { search } }).then(({ data }) => setProducts(data)).catch(() => setError('Unable to load inventory products.'))
    apiClient.get<{ data: Arrangement[] }>('/admin/inventory/arrangements').then(({ data }) => setArrangements(data)).catch(() => setError('Unable to load arrangement requests.'))
  }
  useEffect(() => { load() }, []) // eslint-disable-line react-hooks/exhaustive-deps
  const recordSale = async () => {
    if (!selected) return
    setError(''); setMessage('')
    try {
      await apiClient.post('/admin/inventory/offline-sales', { productId: selected._id, quantity, sellingPrice: Number(sellingPrice), channel: 'OFFLINE' })
      setMessage('Offline sale recorded and stock reduced atomically.'); setSelected(null); setSellingPrice(''); load()
    } catch (saleError) { setError(saleError instanceof Error ? saleError.message : 'Unable to record offline sale.') }
  }
  const updateArrangement = async (item: Arrangement, status: string) => {
    try { await apiClient.patch(`/admin/inventory/arrangements/${item._id}`, { status, agreedUnitPrice: Number(priceDrafts[item._id] || item.productId?.price || 0), paymentVerified: status === 'ADVANCE_VERIFIED' }); setMessage(`${item.requestNumber} moved to ${status}.`); load() } catch { setError('That arrangement transition was rejected by the workflow.') }
  }
  return <>
    <PageHeader title="Inventory & Arrangements" description="Record offline sales, review stock history and process arrange-on-request requests." />
    {message && <p className="alert alert--success">{message}</p>}{error && <p className="alert alert--error">{error}</p>}
    <div className="card" style={{ padding: 24, marginBottom: 24 }}><h3>Offline sale</h3><div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'end' }}><label style={{ flex: '1 1 260px' }}>Search product<input value={search} onChange={e => setSearch(e.target.value)} placeholder="Name, SKU or brand" style={{ width: '100%' }} /></label><Button onClick={load}>Search</Button></div><div style={{ marginTop: 16, display: 'grid', gap: 8 }}>{products.map(product => <button key={product._id} type="button" onClick={() => { setSelected(product); setSellingPrice(String(product.price || '')) }} style={{ textAlign: 'left', padding: 12, background: selected?._id === product._id ? 'var(--color-surface-muted)' : 'transparent', border: '1px solid var(--color-border)', color: 'inherit' }}><strong>{product.name}</strong> · {product.sku || 'No SKU'} · Stock: {product.inventoryMode === 'NUMERIC' ? product.stock ?? 0 : product.availabilityStatus}</button>)}</div>{selected && <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginTop: 16 }}><label>Quantity<input type="number" min={1} value={quantity} onChange={e => setQuantity(Number(e.target.value))} /></label><label>Selling price<input type="number" min={0} value={sellingPrice} onChange={e => setSellingPrice(e.target.value)} /></label><Button variant="primary" onClick={recordSale}>Record offline sale</Button></div>}</div>
    <div className="card" style={{ padding: 24 }}><h3>Arrange-on-request queue</h3>{arrangements.length === 0 ? <p className="text-muted">No arrangement requests.</p> : arrangements.map(item => <div key={item._id} style={{ display: 'flex', gap: 16, alignItems: 'center', borderTop: '1px solid var(--color-border)', padding: '14px 0', flexWrap: 'wrap' }}><span><strong>{item.requestNumber}</strong><br />{item.productId?.name || 'Product'} · Qty {item.quantity} · {item.status}</span>{(['PENDING_REVIEW', 'AVAILABLE_CONFIRMED'].includes(item.status)) && <label>Confirmed price<input type="number" min={0} value={priceDrafts[item._id] ?? item.productId?.price ?? ''} onChange={e => setPriceDrafts(current => ({ ...current, [item._id]: e.target.value }))} /></label>}<span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>{item.status === 'PENDING_REVIEW' && <Button size="sm" onClick={() => updateArrangement(item, 'AVAILABLE_CONFIRMED')}>Confirm availability</Button>}{item.status === 'AVAILABLE_CONFIRMED' && <Button size="sm" onClick={() => updateArrangement(item, 'ADVANCE_PENDING')}>Request 60% advance</Button>}{item.status === 'ADVANCE_SUBMITTED' && <Button size="sm" onClick={() => updateArrangement(item, 'ADVANCE_VERIFIED')}>Verify advance</Button>}{item.status === 'SOURCING' && <Button size="sm" onClick={() => updateArrangement(item, 'READY_TO_DISPATCH')}>Ready to dispatch</Button>}</span></div>)}</div>
  </>
}
