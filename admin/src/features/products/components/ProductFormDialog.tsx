import { useEffect, useState } from 'react'
import { Dialog } from '@/components/ui/Dialog'
import { Button } from '@/components/ui/Button'
import { Field, Input, Select, Textarea } from '@/components/ui/Input'
import { Alert } from '@/components/ui/Feedback'
import { useForm, validate } from '@/hooks/use-form'
import { getUserMessage } from '@/lib/errors'
import { PRODUCT_CATEGORIES, PRODUCT_STATUSES, type Product } from '@/models/Product'
import type { CreateProductPayload } from '@/models/schemas'
import { createProductSchema, updateProductSchema } from '@/models/schemas'
import { useToast } from '@/components/feedback/ToastProvider'
import { useCreateProduct, useUpdateProduct } from '../hooks/use-products'

const CATEGORY_OPTIONS = PRODUCT_CATEGORIES.map((category) => ({
  value: category,
  label: category.charAt(0).toUpperCase() + category.slice(1),
}))

const STATUS_OPTIONS = PRODUCT_STATUSES.map((status) => ({
  value: status,
  label: status.charAt(0).toUpperCase() + status.slice(1),
}))

export interface ProductFormDialogProps {
  open: boolean
  onClose: () => void
  product?: Product
}

interface FormValues {
  name: string
  description: string
  category: string
  price: string
  inventory: string
  status: string
  image: string
  inventoryMode: 'NUMERIC' | 'AVAILABILITY_BASED' | 'SUPPLIER_BASED'
  availabilityStatus: 'IN_STOCK' | 'OUT_OF_STOCK' | 'AVAILABLE_TO_ORDER'
  variantJson: string
  productType: 'PHYSICAL' | 'DIGITAL'
  specificationsJson: string
  attributesJson: string
  digitalConfigJson: string
  physicalConfigJson: string
  seoJson: string
}

/** Create/edit product dialog with shared-schema validation. */
export function ProductFormDialog({ open, onClose, product }: ProductFormDialogProps) {
  const isEdit = product !== undefined
  const toast = useToast()
  const createProduct = useCreateProduct()
  const updateProduct = useUpdateProduct()
  const [serverError, setServerError] = useState<string | null>(null)

  const isSubmitting = createProduct.isPending || updateProduct.isPending

  const form = useForm<FormValues, Record<string, string>>({
    name: '',
    description: '',
    category: 'electronics',
    price: '',
    inventory: '0',
    status: 'draft',
    image: '',
    inventoryMode: 'NUMERIC',
    availabilityStatus: 'OUT_OF_STOCK',
    variantJson: '',
    productType: 'PHYSICAL',
    specificationsJson: '',
    attributesJson: '',
    digitalConfigJson: '',
    physicalConfigJson: '',
    seoJson: '',
  })

  useEffect(() => {
    if (!open) return
    form.setValues({
      name: product?.name ?? '',
      description: product?.description ?? '',
      category: product?.category ?? 'electronics',
      price: product !== undefined ? String(product.price) : '',
      inventory: product !== undefined ? String(product.inventory) : '0',
      status: product?.status ?? 'draft',
      image: product?.image ?? '',
      inventoryMode: product?.inventoryMode ?? 'NUMERIC',
      availabilityStatus: product?.availabilityStatus ?? 'OUT_OF_STOCK',
      variantJson: product?.variants?.length ? JSON.stringify({ attributes: product.variantAttributes || [], variants: product.variants }, null, 2) : '',
      productType: product?.productType ?? 'PHYSICAL',
      specificationsJson: product?.specifications?.length ? JSON.stringify(product.specifications, null, 2) : '',
      attributesJson: product?.attributes?.length ? JSON.stringify(product.attributes, null, 2) : '',
      digitalConfigJson: product?.digitalConfiguration ? JSON.stringify(product.digitalConfiguration, null, 2) : '',
      physicalConfigJson: product?.physicalConfiguration ? JSON.stringify(product.physicalConfiguration, null, 2) : '',
      seoJson: product?.seo ? JSON.stringify(product.seo, null, 2) : '',
    })
    form.setErrors({})
    setServerError(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, product?.id])

  const handleSubmit = async () => {
    setServerError(null)
    form.setErrors({})

    const schema = isEdit ? updateProductSchema : createProductSchema
    const result = validate(schema, form.values)
    if (!result.ok) {
      form.setErrors(result.fieldErrors)
      return
    }

    try {
      let variantPayload: Record<string, unknown> = {}
      if (form.values.variantJson.trim()) {
        const parsed = JSON.parse(form.values.variantJson) as { attributes?: unknown[]; variants?: unknown[] }
        variantPayload = { variantsEnabled: true, variantAttributes: parsed.attributes || [], variants: parsed.variants || [] }
      }
      const parseJson = (value: string, label: string) => { if (!value.trim()) return undefined; try { return JSON.parse(value) } catch { throw new SyntaxError(`${label} JSON is invalid.`) } }
      const payload = { ...result.data, ...variantPayload,
        productType: form.values.productType,
        specifications: parseJson(form.values.specificationsJson, 'Specifications'),
        attributes: parseJson(form.values.attributesJson, 'Attributes'),
        digitalConfiguration: form.values.productType === 'DIGITAL' ? (parseJson(form.values.digitalConfigJson, 'Digital configuration') || {}) : undefined,
        physicalConfiguration: form.values.productType === 'PHYSICAL' ? (parseJson(form.values.physicalConfigJson, 'Physical configuration') || {}) : undefined,
        seo: parseJson(form.values.seoJson, 'SEO'),
      }
      if (isEdit && product) {
        await updateProduct.mutateAsync({ id: product.id, input: payload })
        toast.success('Changes saved', `${payload.name} was updated.`)
      } else {
        await createProduct.mutateAsync(payload as CreateProductPayload)
        toast.success('Product created', `${payload.name} is now in the catalog.`)
      }
      onClose()
    } catch (error) {
      const fields = (error as { fields?: Record<string, string> }).fields
      if (fields && Object.keys(fields).length > 0) {
        form.setErrors(fields)
      } else {
        setServerError(error instanceof SyntaxError ? error.message : getUserMessage(error))
      }
    }
  }

  const title = isEdit ? `Edit ${product?.name}` : 'New product'

  const handleImageFile = (file: File | undefined) => {
    if (!file) return
    if (!file.type.startsWith('image/')) {
      setServerError('Please choose a valid image file.')
      return
    }
    if (file.size > 2 * 1024 * 1024) {
      setServerError('Image must be 2 MB or smaller.')
      return
    }
    const reader = new FileReader()
    reader.onload = () => {
      if (typeof reader.result === 'string') {
        form.setField('image', reader.result)
        setServerError(null)
      }
    }
    reader.readAsDataURL(file)
  }

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={title}
      description={
        isEdit
          ? 'Update catalog details for this product.'
          : 'Add an item to your product catalog.'
      }
      size="md"
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button variant="primary" onClick={handleSubmit} isLoading={isSubmitting}>
            {isEdit ? 'Save changes' : 'Create product'}
          </Button>
        </>
      }
    >
      {serverError && <Alert tone="destructive">{serverError}</Alert>}

      <Field id="product-name" label="Name" required error={form.errors.name}>
        <Input
          id="product-name"
          value={form.values.name}
          invalid={Boolean(form.errors.name)}
          onChange={(event) => {
            form.setField('name', event.target.value)
            form.clearError('name')
          }}
        />
      </Field>

      <Field
        id="product-description"
        label="Description"
        hint="Optional — shown on the product detail page."
        error={form.errors.description}
      >
        <Textarea
          id="product-description"
          value={form.values.description}
          invalid={Boolean(form.errors.description)}
          onChange={(event) => {
            form.setField('description', event.target.value)
            form.clearError('description')
          }}
        />
      </Field>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
        <Field id="product-category" label="Category" required>
          <Select
            id="product-category"
            options={CATEGORY_OPTIONS}
            value={form.values.category}
            onChange={(event) => form.setField('category', event.target.value)}
          />
        </Field>

        <Field id="product-price" label="Price (PKR)" required error={form.errors.price}>
          <Input
            id="product-price"
            type="number"
            min="0"
            step="0.01"
            placeholder="0.00"
            value={form.values.price}
            invalid={Boolean(form.errors.price)}
            onChange={(event) => {
              form.setField('price', event.target.value)
              form.clearError('price')
            }}
          />
        </Field>

        {form.values.inventoryMode === 'NUMERIC' ? <Field id="product-inventory" label="Inventory quantity" required error={form.errors.inventory}>
          <Input id="product-inventory" type="number" min="0" step="1" value={form.values.inventory} invalid={Boolean(form.errors.inventory)} onChange={(event) => { form.setField('inventory', event.target.value); form.clearError('inventory') }} />
        </Field> : <Field id="product-availability" label="Availability status" required>
          <Select id="product-availability" options={form.values.inventoryMode === 'SUPPLIER_BASED' ? [{ value: 'AVAILABLE_TO_ORDER', label: 'Available to order' }, { value: 'OUT_OF_STOCK', label: 'Out of stock' }] : [{ value: 'IN_STOCK', label: 'In Stock' }, { value: 'OUT_OF_STOCK', label: 'Out of Stock' }]} value={form.values.availabilityStatus} onChange={(event) => form.setField('availabilityStatus', event.target.value as FormValues['availabilityStatus'])} />
        </Field>}
      </div>

      <Field id="product-inventory-mode" label="Inventory mode" hint="Use numeric quantities for stocked products, or availability status when quantity is not known.">
        <Select id="product-inventory-mode" options={[{ value: 'NUMERIC', label: 'Quantity-based inventory' }, { value: 'AVAILABILITY_BASED', label: 'Availability-based inventory' }, { value: 'SUPPLIER_BASED', label: 'Supplier-based availability' }]} value={form.values.inventoryMode} onChange={(event) => { const mode = event.target.value as FormValues['inventoryMode']; form.setField('inventoryMode', mode); if (mode === 'SUPPLIER_BASED') form.setField('availabilityStatus', 'AVAILABLE_TO_ORDER') }} />
      </Field>

      <Field id="product-status" label="Status" required>
        <Select
          id="product-status"
          options={STATUS_OPTIONS}
          value={form.values.status}
          onChange={(event) => form.setField('status', event.target.value)}
        />
      </Field>

      <Field id="product-type" label="Product type" hint="Choose Digital only when secure delivery will be configured separately.">
        <Select id="product-type" options={[{ value: 'PHYSICAL', label: 'Physical product' }, { value: 'DIGITAL', label: 'Digital product' }]} value={form.values.productType} onChange={(event) => form.setField('productType', event.target.value as FormValues['productType'])} />
      </Field>

      <Field id="product-specifications" label="Specifications (optional JSON)" hint={'Example: [{"key":"material","label":"Material","value":"Stainless steel","public":true}]. Only configured public fields appear on the storefront.'}>
        <Textarea id="product-specifications" value={form.values.specificationsJson} onChange={(event) => form.setField('specificationsJson', event.target.value)} />
      </Field>

      <Field id="product-attributes" label="Dynamic attributes (optional JSON)" hint={'Example: [{"key":"color","label":"Color","type":"color","allowedValues":["Black","White"],"variant":true}].'}>
        <Textarea id="product-attributes" value={form.values.attributesJson} onChange={(event) => form.setField('attributesJson', event.target.value)} />
      </Field>

      {form.values.productType === 'DIGITAL' ? <Field id="product-digital-config" label="Digital delivery metadata (optional JSON)" hint="Public metadata only. Never place files, private storage paths or license keys here.">
        <Textarea id="product-digital-config" value={form.values.digitalConfigJson} onChange={(event) => form.setField('digitalConfigJson', event.target.value)} />
      </Field> : <Field id="product-physical-config" label="Physical product metadata (optional JSON)" hint="Weight, dimensions, warranty and ownership metadata. Inventory remains controlled separately.">
        <Textarea id="product-physical-config" value={form.values.physicalConfigJson} onChange={(event) => form.setField('physicalConfigJson', event.target.value)} />
      </Field>}

      <Field id="product-seo" label="SEO metadata (optional JSON)" hint={'Example: {"title":"Product title","description":"Short search description","canonicalPath":"/products/example"}.'}>
        <Textarea id="product-seo" value={form.values.seoJson} onChange={(event) => form.setField('seoJson', event.target.value)} />
      </Field>

      <Field id="product-variants" label="Variants (optional JSON)" hint={'Use {"attributes":[{"name":"Color","type":"color","values":["Black","White"]}],"variants":[{"key":"Color=Black","options":{"Color":"Black"},"sku":"SKU-BLK","price":1290,"inventoryMode":"NUMERIC","stock":5,"availabilityStatus":"IN_STOCK"}]}'}>
        <Textarea id="product-variants" value={form.values.variantJson} onChange={(event) => form.setField('variantJson', event.target.value)} />
      </Field>

      <Field id="product-image" label="Product image" hint="Upload an image up to 2 MB, or paste an image URL.">
        <div style={{ display: 'grid', gap: 10 }}>
          <Input id="product-image-url" type="url" placeholder="https://example.com/product-image.jpg" value={form.values.image.startsWith('data:') ? '' : form.values.image} onChange={(event) => form.setField('image', event.target.value)} />
          <Input id="product-image-file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" onChange={(event) => handleImageFile(event.target.files?.[0])} />
          {form.values.image && <img src={form.values.image} alt="Product preview" style={{ width: 112, height: 80, objectFit: 'cover', borderRadius: 10, border: '1px solid var(--color-border)' }} />}
        </div>
      </Field>
    </Dialog>
  )
}
