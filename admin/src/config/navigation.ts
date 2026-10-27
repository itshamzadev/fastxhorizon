import {
  BarChart3,
  LayoutDashboard,
  Mail,
  CreditCard,
  Package,
  Settings,
  ShoppingCart,
  Users,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

export interface NavItem {
  label: string
  to: string
  icon: LucideIcon
  /** When true, only this exact path is active (not nested routes). */
  end?: boolean
}

export interface NavSection {
  id: string
  /** Omit for an unlabeled group. */
  label?: string
  items: NavItem[]
}

/**
 * Sidebar navigation is data-driven so adding a section or feature route is a
 * one-line change here — no layout component edits required.
 */
export const NAV_SECTIONS: NavSection[] = [
  {
    id: 'overview',
    label: 'Overview',
    items: [
      { label: 'Dashboard', to: '/dashboard', icon: LayoutDashboard, end: true },
      { label: 'Analytics', to: '/dashboard/analytics', icon: BarChart3 },
    ],
  },
  {
    id: 'management',
    label: 'Management',
    items: [
      { label: 'Users', to: '/dashboard/users', icon: Users },
      { label: 'Products', to: '/dashboard/products', icon: Package },
      { label: 'Orders', to: '/dashboard/orders', icon: ShoppingCart },
      { label: 'Newsletter', to: '/dashboard/newsletter', icon: Mail },
      { label: 'Payments', to: '/dashboard/payments', icon: CreditCard },
    ],
  },
  {
    id: 'account',
    label: 'Account',
    items: [{ label: 'Settings', to: '/dashboard/settings', icon: Settings }],
  },
]
