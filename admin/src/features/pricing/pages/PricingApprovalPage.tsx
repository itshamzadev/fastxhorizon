import { useEffect, useState } from 'react'
import { CheckCircle, Save } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { Select } from '@/components/ui/Input'
import { TableRoot, Td, Th, THead, Tr } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { EmptyState, ErrorState } from '@/components/ui/Feedback'
import { formatCurrency } from '@/lib/format'
import { getUserMessage } from '@/lib/errors'
import { pricingService, type PricingProduct } from '../pricing.service'

export function PricingApprovalPage() {
  const [items, setItems] = useState<PricingProduct[]>([]); const [status, setStatus] = useState('REVIEW_REQUIRED'); const [loading, setLoading] = useState(true); const [error, setError] = useState<unknown>(null); const [selected, setSelected] = useState<Set<string>>(new Set()); const [saving, setSaving] = useState<string | null>(null)
  const load = () => { setLoading(true); setError(null); pricingService.list({ pricingStatus: status }).then((result) => setItems(result.data)).catch(setError).finally(() => setLoading(false)) }
  useEffect(load, [status])
  const save = async (product: PricingProduct, form: HTMLFormElement) => { const data = new FormData(form); const mode = String(data.get('mode') || 'manual') as 'fixed_profit' | 'percentage_markup' | 'manual'; setSaving(product.id); try { const result = await pricingService.update(product.id, { mode, confirmedSupplierCost: Number(data.get('cost')) || null, operationalCostEstimate: Number(data.get('operations')) || 0, targetProfit: Number(data.get('targetProfit')) || null, markupPercent: Number(data.get('markupPercent')) || null, sellingPrice: Number(data.get('price')) || null }); setItems((current) => current.map((item) => item.id === product.id ? result.data : item)) } catch (cause) { setError(cause) } finally { setSaving(null) } }
  const publish = async () => { if (!selected.size) return; try { await pricingService.publish([...selected]); setSelected(new Set()); load() } catch (cause) { setError(cause) } }
  return <>
    <PageHeader title="Pricing & Approval" description="Review supplier pricing privately, then approve and publish selected products." actions={<div style={{ display: 'flex', gap: 8 }}><Button variant="secondary" onClick={async () => { if (!selected.size) return; try { await pricingService.approve([...selected]); setSelected(new Set()); load() } catch (cause) { setError(cause) } }} disabled={!selected.size}><CheckCircle size={15} /> Approve selected</Button><Button variant="primary" onClick={publish} disabled={!selected.size}><CheckCircle size={15} /> Publish selected</Button></div>} />
    <div className="table__toolbar"><Select aria-label="Pricing status" value={status} onChange={(event) => setStatus(event.target.value)} options={[{ value: 'REVIEW_REQUIRED', label: 'Review required' }, { value: 'APPROVED', label: 'Approved' }, { value: 'MANUAL_OVERRIDE', label: 'Manual override' }]} style={{ width: 190 }} /></div>
    {error ? <ErrorState message={getUserMessage(error)} onRetry={load} /> : <TableRoot caption="Supplier pricing approval table"><THead><tr><Th><span className="visually-hidden">Select</span></Th><Th>Product</Th><Th>Supplier / SKU</Th><Th>Source price</Th><Th>Confirmed cost</Th><Th>Final price</Th><Th>Status</Th><Th>Save</Th></tr></THead><tbody>
      {loading && <tr><Td colSpan={8}>Loading pricing records…</Td></tr>}
      {!loading && items.length === 0 && <tr><td colSpan={8}><EmptyState title="No pricing records" description="No products match this pricing status." /></td></tr>}
      {items.map((product) => <PricingRow key={product.id} product={product} checked={selected.has(product.id)} onCheck={(checked) => setSelected((current) => { const next = new Set(current); checked ? next.add(product.id) : next.delete(product.id); return next })} onSave={save} saving={saving === product.id} />)}
    </tbody></TableRoot>}
  </>
}

function PricingRow({ product, checked, onCheck, onSave, saving }: { product: PricingProduct; checked: boolean; onCheck: (value: boolean) => void; onSave: (product: PricingProduct, form: HTMLFormElement) => void; saving: boolean }) {
  return <Tr><Td><input type="checkbox" aria-label={`Select ${product.name}`} checked={checked} onChange={(event) => onCheck(event.target.checked)} /></Td><Td><strong>{product.name}</strong><br /><small>{product.category}</small></Td><Td>{product.supplierName || '—'}<br /><small>{product.supplierSKU || product.sku || '—'}</small></Td><Td>{product.sourcePrice === null ? '—' : formatCurrency(product.sourcePrice)}</Td><Td><form id={`pricing-${product.id}`} onSubmit={(event) => { event.preventDefault(); onSave(product, event.currentTarget) }}><select name="mode" aria-label={`Pricing mode for ${product.name}`} defaultValue="manual"><option value="manual">Manual</option><option value="fixed_profit">Fixed profit</option><option value="percentage_markup">Markup %</option></select><input name="cost" type="number" min="0" step="0.01" defaultValue={product.confirmedSupplierCost ?? ''} placeholder="Cost" aria-label={`Confirmed cost for ${product.name}`} style={{ width: 85 }} /><input name="operations" type="number" min="0" step="0.01" defaultValue={product.operationalCostEstimate ?? 0} placeholder="Ops" aria-label={`Operational cost for ${product.name}`} style={{ width: 75 }} /><input name="targetProfit" type="number" min="0" step="0.01" defaultValue={product.targetProfit ?? ''} placeholder="Profit" aria-label={`Target profit for ${product.name}`} style={{ width: 75 }} /><input name="markupPercent" type="number" min="0" step="0.01" placeholder="Markup %" aria-label={`Markup percentage for ${product.name}`} style={{ width: 75 }} /></form></Td><Td><input form={`pricing-${product.id}`} name="price" type="number" min="0.01" step="0.01" defaultValue={product.sellingPrice ?? ''} aria-label={`Selling price for ${product.name}`} style={{ width: 105 }} /></Td><Td><Badge tone={product.pricingStatus === 'REVIEW_REQUIRED' ? 'warning' : 'success'}>{product.pricingStatus}</Badge></Td><Td><Button size="sm" variant="secondary" type="submit" form={`pricing-${product.id}`} isLoading={saving}><Save size={14} /> Save</Button></Td></Tr>
}
