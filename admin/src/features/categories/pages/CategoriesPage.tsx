import { useEffect, useMemo, useState } from 'react'
import { FolderTree, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { useAuth } from '@/app/providers/AuthProvider'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { Input, Select, Textarea } from '@/components/ui/Input'
import { Alert, EmptyState, ErrorState } from '@/components/ui/Feedback'
import { Badge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { TableRoot, Td, Th, THead, Tr } from '@/components/ui/Table'
import { getUserMessage } from '@/lib/errors'
import { categoryService } from '@/services/categories/categories.service'
import type { Category, CategoryInput } from '@/models/Category'

const emptyForm: CategoryInput = { name: '', slug: '', parentSlug: null, description: '', image: '', active: true }

function toSlug(value: string): string {
  return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
}

export function CategoriesPage() {
  const { user } = useAuth()
  const [categories, setCategories] = useState<Category[]>([])
  const [form, setForm] = useState<CategoryInput>(emptyForm)
  const [editing, setEditing] = useState<Category | null>(null)
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const load = async () => {
    setLoading(true); setError(null)
    try { const response = await categoryService.list(); setCategories(response.data) } catch (loadError) { setError(getUserMessage(loadError)) } finally { setLoading(false) }
  }
  useEffect(() => { if (user?.role === 'super_admin') void load(); else setLoading(false) }, [user?.role])

  const parents = useMemo(() => categories.filter((category) => !category.parentSlug), [categories])
  const visible = useMemo(() => categories.filter((category) => `${category.name} ${category.slug}`.toLowerCase().includes(search.toLowerCase().trim())), [categories, search])

  if (user?.role !== 'super_admin') return <ErrorState title="Access denied" message="Only a verified Super Admin can manage categories." />

  const startCreate = (parentSlug: string | null = null) => { setEditing(null); setForm({ ...emptyForm, parentSlug }); setNotice(null) }
  const startEdit = (category: Category) => { setEditing(category); setForm({ name: category.name, slug: category.slug, parentSlug: category.parentSlug, description: category.description, image: category.image, active: category.active }); setNotice(null) }
  const save = async () => {
    setError(null); setNotice(null)
    if (form.name.trim().length < 2) return setError('Category name must be at least 2 characters.')
    setSaving(true)
    try {
      const payload = { ...form, name: form.name.trim(), slug: form.slug?.trim() || toSlug(form.name) }
      if (editing) { const updated = await categoryService.update(editing.id, payload); setCategories((items) => items.map((item) => item.id === updated.id ? { ...item, ...updated } : item)); setNotice('Category updated successfully.') }
      else { const created = await categoryService.create(payload); setCategories((items) => [...items, created]); setNotice('Category created successfully.') }
      setEditing(null); setForm(emptyForm)
    } catch (saveError) { setError(getUserMessage(saveError)) } finally { setSaving(false) }
  }
  const remove = async (category: Category) => {
    if (!window.confirm(`Delete ${category.name}? Linked products or subcategories will block deletion.`)) return
    try { await categoryService.remove(category.id); setCategories((items) => items.filter((item) => item.id !== category.id)); setNotice('Category deleted successfully.'); setError(null) } catch (removeError) { setError(getUserMessage(removeError)) }
  }

  return <>
    <PageHeader title="Categories" description="Manage the two-level storefront hierarchy: categories and subcategories." actions={<div style={{ display: 'flex', gap: 8 }}><Button variant="secondary" disabled={!parents.length} onClick={() => startCreate(parents[0]?.slug || null)}><Plus size={15} />New subcategory</Button><Button variant="primary" onClick={() => startCreate()}><Plus size={15} />New category</Button></div>} />
    {error && <Alert tone="destructive">{error}</Alert>}
    {notice && <Alert tone="success">{notice}</Alert>}
    <div className="table__toolbar"><div style={{ position: 'relative', flex: 1, maxWidth: 360 }}><Search size={15} style={{ position: 'absolute', left: 10, top: 10, color: 'var(--muted-foreground)' }} /><Input aria-label="Search categories" placeholder="Search categories" value={search} onChange={(event) => setSearch(event.target.value)} style={{ paddingLeft: 32 }} /></div><Button variant="secondary" onClick={() => startCreate()}><Plus size={15} />Add category</Button></div>
    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.6fr) minmax(280px, .8fr)', gap: 20, alignItems: 'start' }}>
      <Card>
        {loading ? <div style={{ padding: 24 }}>Loading categories…</div> : visible.length === 0 ? <EmptyState icon={<FolderTree size={20} />} title="No categories found" description="Create a top-level category to begin." /> : <TableRoot caption="Categories and subcategories"><THead><tr><Th>Name</Th><Th>Level</Th><Th>Status</Th><Th>Products</Th><Th>Actions</Th></tr></THead><tbody>{visible.map((category) => <Tr key={category.id}><Td><div style={{ paddingLeft: category.parentSlug ? 20 : 0 }}><strong>{category.parentSlug ? '↳ ' : ''}{category.name}</strong><small className="table__cell-muted" style={{ display: 'block' }}>{category.parentSlug ? `Under ${parents.find((parent) => parent.slug === category.parentSlug)?.name || category.parentSlug}` : category.slug}</small></div></Td><Td>{category.parentSlug ? <Badge tone="neutral">Subcategory</Badge> : <Badge tone="primary">Category</Badge>}</Td><Td><Badge tone={category.active ? 'success' : 'neutral'} dot>{category.active ? 'Active' : 'Inactive'}</Badge></Td><Td>{category.productCount}{!category.parentSlug && <small className="table__cell-muted" style={{ display: 'block' }}>{category.subcategoryCount} children</small>}</Td><Td><div className="table__cell-actions"><Button size="sm" variant="ghost" aria-label={`Edit ${category.name}`} onClick={() => startEdit(category)}><Pencil size={14} /></Button><Button size="sm" variant="danger-ghost" aria-label={`Delete ${category.name}`} onClick={() => void remove(category)}><Trash2 size={14} /></Button></div></Td></Tr>)}</tbody></TableRoot>}
      </Card>
      <Card><h2 style={{ marginTop: 0, fontSize: 18 }}>{editing ? `Edit ${editing.name}` : form.parentSlug ? 'New subcategory' : 'New category'}</h2><p className="text-sm text-muted">Only Category → Subcategory is supported. A subcategory cannot contain children.</p><label className="field"><span className="field__label">Name <b>*</b></span><Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value, slug: editing ? form.slug : toSlug(event.target.value) })} /></label><label className="field"><span className="field__label">URL slug</span><Input value={form.slug || ''} onChange={(event) => setForm({ ...form, slug: toSlug(event.target.value) })} /></label><label className="field"><span className="field__label">Parent category</span><Select value={form.parentSlug || ''} disabled={Boolean(editing?.parentSlug === null)} onChange={(event) => setForm({ ...form, parentSlug: event.target.value || null })} options={[{ value: '', label: 'Top-level category' }, ...parents.filter((parent) => parent.id !== editing?.id).map((parent) => ({ value: parent.slug, label: parent.name }))]} /></label><label className="field"><span className="field__label">Description</span><Textarea value={form.description || ''} onChange={(event) => setForm({ ...form, description: event.target.value })} /></label><label className="field"><span className="field__label">Image URL</span><Input type="url" value={form.image || ''} onChange={(event) => setForm({ ...form, image: event.target.value })} placeholder="https://…" /></label><label className="field"><span className="field__label">Status</span><Select value={form.active === false ? 'inactive' : 'active'} onChange={(event) => setForm({ ...form, active: event.target.value === 'active' })} options={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]} /></label><div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}><Button variant="ghost" onClick={() => { setEditing(null); setForm(emptyForm) }}>Clear</Button><Button variant="primary" isLoading={saving} onClick={() => void save()}>{editing ? 'Save changes' : 'Create category'}</Button></div></Card>
    </div>
  </>
}
