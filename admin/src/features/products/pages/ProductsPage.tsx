import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Eye, MoreHorizontal, Package, PackagePlus, Pencil, Trash2 } from 'lucide-react'
import type { Product, ProductStatus } from '@/models/Product'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { SearchInput } from '@/components/forms/SearchInput'
import { Select } from '@/components/ui/Input'
import {
  SortableTh,
  TableRoot,
  Td,
  Th,
  THead,
  Tr,
  type SortState,
} from '@/components/ui/Table'
import { Pagination } from '@/components/ui/Pagination'
import { EmptyState, ErrorState } from '@/components/ui/Feedback'
import { DropdownMenu } from '@/components/ui/DropdownMenu'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { getUserMessage } from '@/lib/errors'
import { formatCurrency } from '@/lib/format'
import { PRODUCT_STATUS_OPTIONS, formatCategory } from '@/components/display/status-badges'
import { PRODUCT_CATEGORIES } from '@/models/Product'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { useProducts } from '../hooks/use-products'
import { ProductFormDialog } from '../components/ProductFormDialog'
import { DeleteProductDialog } from '../components/DeleteProductDialog'

const PAGE_SIZE = 10

function productIsInStock(product: Product): boolean {
  if (product.inventoryMode === 'SUPPLIER_BASED') return product.availabilityStatus === 'AVAILABLE_TO_ORDER'
  if (product.inventoryMode === 'AVAILABILITY_BASED') return product.availabilityStatus === 'IN_STOCK'
  return product.inventory > 0
}

function inventoryTone(product: Product): 'destructive' | 'warning' | 'success' | 'neutral' {
  if (productIsInStock(product)) return ['AVAILABILITY_BASED', 'SUPPLIER_BASED'].includes(product.inventoryMode || '') ? 'success' : product.inventory <= 10 ? 'warning' : 'neutral'
  if (product.inventory === 0) return 'destructive'
  return 'neutral'
}

/**
 * Product catalog management — mirrors the users feature to demonstrate how
 * a second CRUD domain reuses the same primitives with zero new abstractions.
 */
export function ProductsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const navigate = useNavigate()

  const search = searchParams.get('search') ?? ''
  const debouncedSearch = useDebouncedValue(search)
  const category = searchParams.get('category') ?? 'all'
  const subcategory = searchParams.get('subcategory') ?? 'all'
  const status = (searchParams.get('status') ?? 'all') as ProductStatus | 'all'
  const sortParam = searchParams.get('sort') ?? ''
  const sortField = sortParam.startsWith('-') ? sortParam.slice(1) : sortParam
  const sortDirection: SortState =
    sortField === '' ? null : sortParam.startsWith('-') ? 'desc' : 'asc'
  const page = Math.max(1, Number(searchParams.get('page')) || 1)

  const updateParam = (key: string, value: string | null) => {
    const next = new URLSearchParams(searchParams)
    if (!value) next.delete(key)
    else next.set(key, value)
    if (key === 'category') next.delete('subcategory')
    if (key !== 'page' && key !== 'create') next.delete('page')
    setSearchParams(next, { replace: true })
  }

  const products = useProducts({
    page,
    pageSize: PAGE_SIZE,
    search: debouncedSearch || undefined,
    category,
    subcategory,
    status,
    sort: sortParam || undefined,
  })

  const [createOpen, setCreateOpen] = useState(searchParams.get('create') === '1')
  const [editingProduct, setEditingProduct] = useState<Product | null>(null)
  const [deletingProduct, setDeletingProduct] = useState<Product | null>(null)

  useEffect(() => {
    if (createOpen && searchParams.get('create') !== '1') updateParam('create', '1')
    if (!createOpen && searchParams.get('create') === '1') updateParam('create', null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [createOpen])

  const toggleSort = (field: string) => {
    if (sortField !== field) return updateParam('sort', field)
    if (sortDirection === 'asc') return updateParam('sort', `-${field}`)
    updateParam('sort', null)
  }

  const hasFilters = debouncedSearch !== '' || category !== 'all' || subcategory !== 'all' || status !== 'all'
  const filters = products.data?.meta.filters
  const categoryOptions = filters?.categories?.length
    ? filters.categories
    : PRODUCT_CATEGORIES.map((value) => ({ name: formatCategory(value), slug: value, productCount: 0, subcategories: [] }))
  const selectedCategory = categoryOptions.find((option) => option.slug === category)
  const subcategoryOptions = selectedCategory?.subcategories || []
  const selectedSubcategory = subcategoryOptions.find((option) => option.slug === subcategory)
  const selectedLabel = selectedSubcategory?.name
    || (subcategory !== 'all' ? subcategory : selectedCategory?.name || (category === 'uncategorised' ? 'Uncategorised' : 'All products'))
  const selectedCount = selectedSubcategory?.productCount
    ?? selectedCategory?.productCount
    ?? (category === 'uncategorised' ? filters?.uncategorised : '—')

  return (
    <>
      <PageHeader
        title="Products"
        description="Manage your catalog: pricing, inventory and availability."
        actions={
          <Button variant="primary" onClick={() => setCreateOpen(true)}>
            <PackagePlus size={15} aria-hidden="true" />
            New product
          </Button>
        }
      />

      <div className="table__toolbar">
        <SearchInput
          value={search}
          onChange={(value) => updateParam('search', value || null)}
          placeholder="Search name or SKU…"
          label="Search products"
        />
        <Select
          aria-label="Filter by category"
          options={[
            { value: 'all', label: 'All categories' },
            ...categoryOptions.map((option) => ({ value: option.slug, label: `${option.name} (${option.productCount})` })),
            { value: 'uncategorised', label: `Uncategorised (${filters?.uncategorised ?? 0})` },
          ]}
          value={category}
          onChange={(event) =>
            updateParam(
              'category',
              event.target.value === 'all' ? null : event.target.value,
            )
          }
          style={{ width: 170 }}
        />
        <Select
          aria-label="Filter by subcategory"
          options={[
            { value: 'all', label: 'All subcategories' },
            ...subcategoryOptions.map((option) => ({ value: option.slug, label: `${option.name} (${option.productCount})` })),
          ]}
          value={subcategory}
          disabled={!selectedCategory || subcategoryOptions.length === 0}
          onChange={(event) => updateParam('subcategory', event.target.value === 'all' ? null : event.target.value)}
          style={{ width: 190 }}
        />
        <Select
          aria-label="Filter by status"
          options={[{ value: 'all', label: 'All statuses' }, ...PRODUCT_STATUS_OPTIONS]}
          value={status}
          onChange={(event) =>
            updateParam(
              'status',
              event.target.value === 'all' ? null : event.target.value,
            )
          }
          style={{ width: 150 }}
        />
        {hasFilters && <Button variant="ghost" onClick={() => { const next = new URLSearchParams(searchParams); ['search', 'category', 'subcategory', 'status', 'page'].forEach((key) => next.delete(key)); setSearchParams(next, { replace: true }) }}>Clear filters</Button>}
      </div>

      <div className="text-sm text-muted" style={{ display: 'flex', gap: 16, flexWrap: 'wrap', margin: '10px 0 16px' }}>
        <span><strong>{filters?.total ?? '—'}</strong> all products</span>
        <span><strong>{selectedCount}</strong> in {selectedLabel}</span>
        <span><strong>{filters?.statusCounts?.active ?? '—'}</strong> active</span>
        <span><strong>{filters?.statusCounts?.draft ?? '—'}</strong> draft</span>
        <span><strong>{filters?.uncategorised ?? '—'}</strong> uncategorised</span>
      </div>

      {products.isError ? (
        <ErrorState
          message={getUserMessage(products.error)}
          onRetry={() => products.refetch()}
        />
      ) : (
        <>
            <TableRoot caption="Product catalog with category, price, inventory and status">
            <THead>
              <tr>
                <SortableTh
                  label="Name"
                  state={colState('name', sortField, sortDirection)}
                  onToggle={() => toggleSort('name')}
                />
                <Th>SKU</Th>
                <SortableTh
                  label="Category"
                  state={colState('category', sortField, sortDirection)}
                  onToggle={() => toggleSort('category')}
                />
                <Th>Subcategory</Th>
                <SortableTh
                  label="Price"
                  state={colState('price', sortField, sortDirection)}
                  onToggle={() => toggleSort('price')}
                  align="end"
                />
                <SortableTh
                  label="Inventory"
                  state={colState('inventory', sortField, sortDirection)}
                  onToggle={() => toggleSort('inventory')}
                  align="end"
                />
                <SortableTh
                  label="Status"
                  state={colState('status', sortField, sortDirection)}
                  onToggle={() => toggleSort('status')}
                />
                <Th>
                  <span className="visually-hidden">Actions</span>
                </Th>
              </tr>
            </THead>
            <tbody>
              {products.isPending &&
                [1, 2, 3, 4, 5].map((row) => (
                  <tr key={row}>
                    {[1, 2, 3, 4, 5, 6, 7, 8].map((cell) => (
                      <Td key={cell}>
                        <Skeleton style={{ height: 16, width: cell === 1 ? 150 : 80 }} />
                      </Td>
                    ))}
                  </tr>
                ))}

              {products.isSuccess && products.data.meta.total === 0 && (
                <tr>
                  <td colSpan={8}>
                    <EmptyState
                      icon={<Package size={18} aria-hidden="true" />}
                      title={hasFilters ? 'No matching products' : 'Catalog is empty'}
                      description={
                        hasFilters
                          ? 'Try adjusting your search or filters.'
                          : 'Add your first product to start tracking inventory.'
                      }
                      action={
                        hasFilters ? undefined : (
                          <Button variant="primary" onClick={() => setCreateOpen(true)}>
                            <PackagePlus size={15} aria-hidden="true" />
                            New product
                          </Button>
                        )
                      }
                    />
                  </td>
                </tr>
              )}

              {products.isSuccess &&
                products.data.data.map((product) => (
                  <Tr key={product.id}>
                    <Td>
                      <Link
                        to={`/dashboard/products/${product.id}`}
                        className="table__cell-primary truncate"
                        style={{ display: 'inline-block', maxWidth: 260 }}
                      >
                        {product.name}
                      </Link>
                    </Td>
                    <Td className="mono table__cell-muted">{product.sku}</Td>
                    <Td className="table__cell-muted">
                      {formatCategory(product.category)}
                    </Td>
                    <Td className="table__cell-muted">
                      {product.subcategory ? formatCategory(product.subcategory) : '—'}
                    </Td>
                    <Td className="table__cell-num">
                      {formatCurrency(product.price, { precise: true })}
                    </Td>
                    <Td className="table__cell-num">
                      <Badge tone={inventoryTone(product)}>
                        {['AVAILABILITY_BASED', 'SUPPLIER_BASED'].includes(product.inventoryMode || '')
                          ? (productIsInStock(product) ? (product.inventoryMode === 'SUPPLIER_BASED' ? 'Available to order' : 'In stock') : 'Out of stock')
                          : product.inventory === 0
                            ? 'Out of stock'
                            : `${product.inventory} in stock`}
                      </Badge>
                    </Td>
                    <Td>
                      {product.status === 'active' ? (
                        <Badge tone="success" dot>
                          Active
                        </Badge>
                      ) : product.status === 'draft' ? (
                        <Badge tone="warning" dot>
                          Draft
                        </Badge>
                      ) : (
                        <Badge tone="neutral">Archived</Badge>
                      )}
                    </Td>
                    <Td>
                      <div className="table__cell-actions">
                        <DropdownMenu
                          label={`Actions for ${product.name}`}
                          items={[
                            {
                              label: 'View details',
                              icon: <Eye size={14} aria-hidden="true" />,
                              onSelect: () => navigate(`/dashboard/products/${product.id}`),
                            },
                            {
                              label: 'Edit',
                              icon: <Pencil size={14} aria-hidden="true" />,
                              onSelect: () => setEditingProduct(product),
                            },
                            {
                              label: 'Delete',
                              icon: <Trash2 size={14} aria-hidden="true" />,
                              tone: 'danger',
                              onSelect: () => setDeletingProduct(product),
                            },
                          ]}
                          trigger={(triggerProps) => (
                            <button
                              type="button"
                              className="icon-btn"
                              aria-label={`Actions for ${product.name}`}
                              {...triggerProps}
                            >
                              <MoreHorizontal size={16} aria-hidden="true" />
                            </button>
                          )}
                        />
                      </div>
                    </Td>
                  </Tr>
                ))}
            </tbody>
          </TableRoot>

          {!products.isPending && !products.isError && products.data !== undefined && (
            <Pagination
              meta={products.data.meta}
              onPageChange={(p) => updateParam('page', String(p))}
              noun="products"
            />
          )}
        </>
      )}

      <ProductFormDialog open={createOpen} onClose={() => setCreateOpen(false)} />
      <ProductFormDialog
        open={editingProduct !== null}
        onClose={() => setEditingProduct(null)}
        product={editingProduct ?? undefined}
      />
      <DeleteProductDialog
        product={deletingProduct}
        onClose={() => setDeletingProduct(null)}
      />
    </>
  )
}

function colState(field: string, activeField: string, direction: SortState): SortState {
  return activeField === field ? direction : null
}
