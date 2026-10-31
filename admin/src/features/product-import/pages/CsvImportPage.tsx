import { useMemo, useState } from 'react'
import { CheckCircle, Edit3, FileUp, UploadCloud } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { Input, Select, Textarea } from '@/components/ui/Input'
import { Alert, EmptyState, ErrorState } from '@/components/ui/Feedback'
import { Badge } from '@/components/ui/Badge'
import { Dialog } from '@/components/ui/Dialog'
import { TableRoot, Td, Th, THead, Tr } from '@/components/ui/Table'
import { formatCurrency } from '@/lib/format'
import { getUserMessage } from '@/lib/errors'
import { importService, type ImportPreview, type ImportPreviewProduct } from '../product-import.service'

const PREVIEW_PAGE_SIZE = 25

function cloneProduct(product: ImportPreviewProduct): ImportPreviewProduct {
  return { ...product, images: [...(product.images || [])], tags: [...(product.tags || [])] }
}

export function CsvImportPage() {
  const [file, setFile] = useState<File | null>(null)
  const [paste, setPaste] = useState('')
  const [inputMode, setInputMode] = useState<'upload' | 'paste'>('upload')
  const [preview, setPreview] = useState<ImportPreview | null>(null)
  const [editing, setEditing] = useState<ImportPreviewProduct | null>(null)
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)
  const [result, setResult] = useState<string | null>(null)
  const [mode, setMode] = useState<'new_only' | 'update_existing' | 'skip_existing'>('skip_existing')

  const inspect = async () => {
    const csv = inputMode === 'paste' ? paste : file ? await file.text() : ''
    if (!csv.trim()) return
    setBusy(true); setError(null); setResult(null)
    try {
      const response = await importService.preview(csv, inputMode === 'paste' ? 'pasted-woocommerce.csv' : file?.name || 'woocommerce.csv')
      setPreview(response.data)
      setSelected(new Set(response.data.products.filter((product) => product.validation === 'valid' && !product.duplicate).map((product) => product.supplierKey)))
      setPage(1); setSearch('')
    } catch (cause) { setError(cause) } finally { setBusy(false) }
  }

  const confirm = async () => {
    if (!preview || selected.size === 0) return
    const products = preview.products.filter((product) => selected.has(product.supplierKey))
    if (!window.confirm(`Confirm import of ${products.length} selected YouMart products as Draft?`)) return
    setBusy(true); setError(null)
    try {
      const response = await importService.confirm(preview.id, mode, products)
      setResult(`Import complete: ${response.data.imported} imported, ${response.data.skipped} skipped, ${response.data.failed} failed.`)
      setPreview(null); setSelected(new Set())
    } catch (cause) { setError(cause) } finally { setBusy(false) }
  }

  const chooseFile = (nextFile: File | undefined) => {
    if (!nextFile) return
    if (!/\.csv$/i.test(nextFile.name) || nextFile.size > 10 * 1024 * 1024) { setError(new Error('Choose a CSV file no larger than 10 MB.')); return }
    setFile(nextFile); setInputMode('upload'); setPreview(null); setResult(null); setError(null)
  }

  const filteredProducts = useMemo(() => {
    const query = search.trim().toLowerCase()
    return (preview?.products || []).filter((product) => !query || `${product.name} ${product.sku} ${product.category} ${product.subcategory}`.toLowerCase().includes(query))
  }, [preview, search])
  const pageCount = Math.max(1, Math.ceil(filteredProducts.length / PREVIEW_PAGE_SIZE))
  const pageProducts = filteredProducts.slice((page - 1) * PREVIEW_PAGE_SIZE, page * PREVIEW_PAGE_SIZE)
  const parents = preview?.categoryOptions?.filter((category) => !category.parentSlug) || []
  const editParent = parents.find((category) => category.name === editing?.category || category.slug === editing?.category)
  const editChildren = preview?.categoryOptions?.filter((category) => category.parentSlug === editParent?.slug) || []

  const saveEdit = () => {
    if (!preview || !editing) return
    setPreview({ ...preview, products: preview.products.map((product) => product.supplierKey === editing.supplierKey ? editing : product) })
    setEditing(null)
  }

  const toggle = (key: string) => setSelected((current) => { const next = new Set(current); if (next.has(key)) next.delete(key); else next.add(key); return next })
  const togglePage = () => setSelected((current) => { const next = new Set(current); const allSelected = pageProducts.every((product) => next.has(product.supplierKey)); pageProducts.forEach((product) => allSelected ? next.delete(product.supplierKey) : next.add(product.supplierKey)); return next })

  return <>
    <PageHeader title="CSV Import" description="Import supplier products safely. Preview and edit first; every imported YouMart product remains a draft." />
    <section className="card" style={{ padding: 20, marginBottom: 16 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap' }}><div><Badge tone="primary">Supplier importer</Badge><h2 style={{ margin: '10px 0 4px' }}>YouMart</h2><p className="page-header__description">WooCommerce CSV · Upload or paste raw CSV text</p></div><div style={{ display: 'flex', alignItems: 'center', gap: 8 }}><Button variant={inputMode === 'upload' ? 'primary' : 'secondary'} onClick={() => setInputMode('upload')}><UploadCloud size={15} />Upload CSV</Button><Button variant={inputMode === 'paste' ? 'primary' : 'secondary'} onClick={() => setInputMode('paste')}>Paste CSV Code</Button></div></div>
      {inputMode === 'upload' ? <div onDragOver={(event) => event.preventDefault()} onDrop={(event) => { event.preventDefault(); chooseFile(event.dataTransfer.files?.[0]) }} style={{ border: '1px dashed var(--color-border, #cbd5e1)', borderRadius: 8, padding: 20, marginTop: 16 }}><label htmlFor="woocommerce-csv"><strong>WooCommerce CSV</strong></label><p className="text-sm text-muted">UTF-8 CSV, maximum 10 MB. Multiline quoted descriptions and image lists are supported.</p><input id="woocommerce-csv" type="file" accept=".csv,text/csv" onChange={(event) => chooseFile(event.target.files?.[0])} /><span style={{ marginLeft: 10 }}>{file ? `${file.name} · ${(file.size / 1024).toFixed(1)} KB` : 'or drag a file here'}</span></div> : <label className="field" style={{ marginTop: 16 }}><span className="field__label">Paste WooCommerce CSV code</span><Textarea rows={10} value={paste} onChange={(event) => setPaste(event.target.value)} placeholder="SKU,Type,Name,Published,Short description,Description,Regular price,In stock?,Images,Categories,Supplier,Parent…" /></label>}
      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center', marginTop: 14 }}><Button variant="secondary" onClick={() => void inspect()} disabled={inputMode === 'upload' ? !file : !paste.trim()} isLoading={busy}><FileUp size={15} />Preview products</Button><span className="text-sm text-muted">No database changes happen during preview.</span></div>
    </section>
    {result && <Alert tone="success">{result}</Alert>}
    {error && <ErrorState message={getUserMessage(error)} onRetry={() => void inspect()} />}
    {preview && <>
      <section className="card" style={{ padding: 20, marginBottom: 16 }}><div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}><div><h2 style={{ margin: 0 }}>Step 3 · Preview & map</h2><p className="text-sm text-muted">{preview.filename} · {preview.format || 'WooCommerce'} · expires {new Date(preview.expiresAt).toLocaleString()}</p></div><div style={{ display: 'flex', gap: 8, alignItems: 'center' }}><Select aria-label="Import mode" value={mode} onChange={(event) => setMode(event.target.value as typeof mode)} options={[{ value: 'skip_existing', label: 'Skip existing products' }, { value: 'new_only', label: 'Import new products only' }, { value: 'update_existing', label: 'Update existing supplier records' }]} /><Button variant="primary" disabled={!selected.size} onClick={() => void confirm()} isLoading={busy}><CheckCircle size={15} />Confirm {selected.size} selected</Button></div></div><div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(125px,1fr))', gap: 12, marginTop: 18 }}>{[['Rows', preview.summary.rows], ['Valid', preview.summary.uniqueProducts - preview.summary.invalidRows], ['Duplicates', preview.summary.duplicateSkus || 0], ['Missing prices', preview.summary.missingPrices], ['Selected', selected.size], ['Images', preview.summary.imageRows]].map(([label, value]) => <div key={String(label)}><small>{label}</small><strong style={{ display: 'block', fontSize: 22 }}>{String(value)}</strong></div>)}</div></section>
      <div className="table__toolbar"><Input aria-label="Search preview" placeholder="Search preview products…" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} /><Button variant="secondary" onClick={togglePage}>{pageProducts.length && pageProducts.every((product) => selected.has(product.supplierKey)) ? 'Deselect page' : 'Select page'}</Button><span className="text-sm text-muted">Showing {pageProducts.length} of {filteredProducts.length} · selected {selected.size}</span></div>
      <div style={{ overflowX: 'auto' }}><TableRoot caption="YouMart WooCommerce product preview"><THead><tr><Th><input type="checkbox" aria-label="Select visible products" checked={pageProducts.length > 0 && pageProducts.every((product) => selected.has(product.supplierKey))} onChange={togglePage} /></Th><Th>Image</Th><Th>Product</Th><Th>SKU</Th><Th>Category</Th><Th>Source</Th><Th>Selling</Th><Th>Stock</Th><Th>Status</Th><Th>Action</Th></tr></THead><tbody>{pageProducts.map((product) => <Tr key={product.supplierKey}><Td><input type="checkbox" aria-label={`Select ${product.name}`} checked={selected.has(product.supplierKey)} disabled={product.validation === 'unmatched_category' || product.duplicate} onChange={() => toggle(product.supplierKey)} /></Td><Td>{product.image ? <img src={product.image} alt="" width={44} height={44} style={{ objectFit: 'contain', borderRadius: 6 }} /> : '—'}</Td><Td><strong>{product.name}</strong><small className="table__cell-muted" style={{ display: 'block' }}>{product.imageCount} image(s)</small></Td><Td className="mono">{product.sku || '—'}</Td><Td>{product.category || 'Unmatched'}{product.subcategory && ` / ${product.subcategory}`}</Td><Td>{product.sourcePrice === null ? '—' : formatCurrency(product.sourcePrice)}</Td><Td>{product.sellingPrice ? formatCurrency(product.sellingPrice) : 'Not set'}</Td><Td><Badge tone={product.stockAvailable ? 'success' : 'neutral'}>{product.stockAvailable ? 'In stock' : 'Unavailable'}</Badge></Td><Td><Badge tone={product.duplicate || product.validation !== 'valid' ? 'warning' : 'success'}>{product.duplicate ? 'Duplicate' : product.validation}</Badge></Td><Td><Button size="sm" variant="ghost" aria-label={`Edit ${product.name}`} onClick={() => setEditing(cloneProduct(product))}><Edit3 size={14} /></Button></Td></Tr>)}</tbody></TableRoot></div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 12 }}><span className="text-sm text-muted">Page {page} of {pageCount}</span><div style={{ display: 'flex', gap: 8 }}><Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>Previous</Button><Button size="sm" variant="secondary" disabled={page >= pageCount} onClick={() => setPage((current) => current + 1)}>Next</Button></div></div>
    </>}
    {!preview && !file && !paste && <EmptyState icon={<FileUp size={20} />} title="Choose YouMart CSV input" description="Upload a WooCommerce export or paste its raw CSV content to begin a safe preview." />}
    <Dialog open={editing !== null} onClose={() => setEditing(null)} title="Edit pending product" description="Changes stay in the preview until you explicitly confirm the selected import." size="lg" footer={<><Button variant="ghost" onClick={() => setEditing(null)}>Cancel</Button><Button variant="primary" onClick={saveEdit}>Save preview changes</Button></>}>
      {editing && <div style={{ display: 'grid', gap: 12 }}><label className="field"><span className="field__label">Product name</span><Input value={editing.name} onChange={(event) => setEditing({ ...editing, name: event.target.value })} /></label><label className="field"><span className="field__label">SKU</span><Input value={editing.sku || ''} onChange={(event) => setEditing({ ...editing, sku: event.target.value.toUpperCase(), supplierSKU: event.target.value.toUpperCase() })} /></label><div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}><label className="field"><span className="field__label">Category</span><Select value={editing.category} onChange={(event) => setEditing({ ...editing, category: event.target.value, subcategory: '' })} options={[{ value: '', label: 'Select category' }, ...parents.map((category) => ({ value: category.name, label: category.name }))]} /></label><label className="field"><span className="field__label">Subcategory</span><Select value={editing.subcategory} disabled={!editParent} onChange={(event) => setEditing({ ...editing, subcategory: event.target.value })} options={[{ value: '', label: 'No subcategory' }, ...editChildren.map((category) => ({ value: category.name, label: category.name }))]} /></label></div><div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}><label className="field"><span className="field__label">Source price</span><Input type="number" min="0" value={editing.sourcePrice ?? ''} onChange={(event) => setEditing({ ...editing, sourcePrice: event.target.value === '' ? null : Number(event.target.value) })} /></label><label className="field"><span className="field__label">FASTXHORIZON selling price</span><Input type="number" min="0" value={editing.sellingPrice ?? ''} onChange={(event) => setEditing({ ...editing, sellingPrice: event.target.value === '' ? null : Number(event.target.value) })} /></label></div><label className="field"><span className="field__label">Short description</span><Textarea rows={3} value={editing.shortDescription || ''} onChange={(event) => setEditing({ ...editing, shortDescription: event.target.value })} /></label><label className="field"><span className="field__label">Full HTML description</span><Textarea rows={7} value={editing.descriptionHtml || ''} onChange={(event) => setEditing({ ...editing, descriptionHtml: event.target.value })} /><small className="field__hint">Scripts are removed server-side before persistence.</small></label><label className="field"><span className="field__label">Image URLs, one per line</span><Textarea rows={5} value={(editing.images || []).join('\n')} onChange={(event) => setEditing({ ...editing, images: [...new Set(event.target.value.split(/\r?\n|,/).map((url) => url.trim()).filter(Boolean))] })} /><small className="field__hint">The first URL becomes the primary image.</small></label><label className="field"><span className="field__label">Tags, comma separated</span><Input value={(editing.tags || []).join(', ')} onChange={(event) => setEditing({ ...editing, tags: event.target.value.split(',').map((tag) => tag.trim()).filter(Boolean) })} /></label></div>}
    </Dialog>
  </>
}
