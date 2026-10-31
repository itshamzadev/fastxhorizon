export interface Category {
  id: string
  name: string
  slug: string
  parentSlug: string | null
  description: string
  image: string
  active: boolean
  /** Legacy product values shown in management until explicitly saved. */
  virtual?: boolean
  productCount: number
  subcategoryCount: number
  createdAt?: string
  updatedAt?: string
  subcategories?: Category[]
}

export interface CategoryInput {
  name: string
  slug?: string
  parentSlug?: string | null
  description?: string
  image?: string
  active?: boolean
}
