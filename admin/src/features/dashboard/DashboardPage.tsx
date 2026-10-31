import { useState, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Package, ShoppingCart, TrendingUp, Users } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/Feedback'
import { Skeleton } from '@/components/ui/Skeleton'
import { Badge } from '@/components/ui/Badge'
import { Select } from '@/components/ui/Input'
import { formatCurrency, formatDate } from '@/lib/format'
import { queryKeys } from '@/lib/query-keys'
import { dashboardService } from './dashboard.service'

export function DashboardPage() {
  const [range, setRange] = useState('30d')
  const overview = useQuery({ queryKey: queryKeys.dashboard.overview, queryFn: dashboardService.getOverview })
  const analytics = useQuery({ queryKey: ['dashboard', 'analytics', range], queryFn: () => dashboardService.getAnalytics(range) })
  return <>
    <PageHeader title="FastXHorizon Control Panel" description="Live commerce operations from the local MongoDB database." />
    {overview.isPending && <div className="kpi-grid kpi-grid--3">{[1, 2, 3, 4, 5, 6].map((i) => <Skeleton key={i} style={{ height: 100 }} />)}</div>}
    {overview.isError && <ErrorState message="Could not load live dashboard metrics." onRetry={() => overview.refetch()} />}
    {overview.data && <>
      <div className="kpi-grid kpi-grid--3">
        <Metric title="Customers" value={overview.data.totalCustomers ?? 0} icon={<Users size={18} />} detail={`${overview.data.activeCustomers ?? 0} active`} />
        <Metric title="Products" value={overview.data.totalProducts ?? 0} icon={<Package size={18} />} detail={`${overview.data.outOfStockProducts ?? 0} out of stock`} />
        <Metric title="Orders" value={overview.data.totalOrders ?? 0} icon={<ShoppingCart size={18} />} detail={`${overview.data.pendingOrders ?? 0} pending`} />
        <Metric title="GMV" value={formatCurrency(overview.data.grossMerchandiseValue ?? 0)} icon={<TrendingUp size={18} />} detail="Non-cancelled order value" />
        <Metric title="Collected revenue" value={formatCurrency(overview.data.confirmedCollectedRevenue ?? 0)} icon={<TrendingUp size={18} />} detail="Confirmed payments only" />
        <Metric title="Completed orders" value={overview.data.completedOrders ?? 0} icon={<ShoppingCart size={18} />} detail={`${overview.data.cancelledOrders ?? 0} cancelled`} />
      </div>
      <div className="dashboard-layout-3col" style={{ marginTop: 20 }}>
        <Card><CardHeader><CardTitle>Recent orders</CardTitle></CardHeader><CardContent style={{ padding: 0 }}><SimpleTable headers={['Order', 'Customer', 'Total', 'Status']} rows={(overview.data.recentOrders ?? []).map((order) => [order.number, order.customerName, formatCurrency(order.total), order.status])} /></CardContent></Card>
        <Card><CardHeader><CardTitle>Recent customers</CardTitle></CardHeader><CardContent style={{ padding: 0 }}><SimpleTable headers={['Name', 'Email', 'Joined']} rows={(overview.data.recentCustomers ?? []).map((customer) => [customer.name, customer.email, formatDate(customer.createdAt)])} /></CardContent></Card>
      </div>
      {analytics.isError && <div style={{ marginTop: 20 }}><ErrorState message="Analytics data could not be loaded." onRetry={() => analytics.refetch()} /></div>}
      {analytics.data && <>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'end', gap: 16, marginTop: 28, flexWrap: 'wrap' }}><div><h2 style={{ margin: 0 }}>Executive analytics</h2><p className="text-sm text-muted">Actual order, payment, catalog and customer records. Timezone: {analytics.data.timezone}.</p></div><Select aria-label="Analytics date range" options={[{ value: 'today', label: 'Today' }, { value: 'yesterday', label: 'Yesterday' }, { value: '7d', label: 'Last 7 days' }, { value: '30d', label: 'Last 30 days' }, { value: '90d', label: 'Last 90 days' }, { value: 'month', label: 'This month' }, { value: 'year', label: 'This year' }]} value={range} onChange={(event) => setRange(event.target.value)} /></div>
        <div className="kpi-grid kpi-grid--3" style={{ marginTop: 16 }}><Metric title="GMV in period" value={formatCurrency(analytics.data.orderTotals.gmv)} icon={<TrendingUp size={18} />} detail={`${analytics.data.orderTotals.orders} orders; cancelled value excluded`} /><Metric title="Collected payments" value={formatCurrency(analytics.data.payments.verified?.amount ?? 0)} icon={<TrendingUp size={18} />} detail={`${analytics.data.payments.verified?.count ?? 0} verified payments`} /><Metric title="New customers" value={analytics.data.newCustomers} icon={<Users size={18} />} detail="Registrations in selected period" /><Metric title="Published products" value={analytics.data.catalog.published} icon={<Package size={18} />} detail={`${analytics.data.catalog.drafts} drafts remain private`} /><Metric title="Digital products" value={analytics.data.catalog.digital} icon={<Package size={18} />} detail="Configured product records" /><Metric title="Numeric out of stock" value={analytics.data.catalog.numericOutOfStock} icon={<Package size={18} />} detail="Quantity-based products only" /></div>
        <div className="dashboard-layout-3col" style={{ marginTop: 20 }}><Card><CardHeader><CardTitle>Sales by day</CardTitle></CardHeader><CardContent style={{ padding: 0 }}><SimpleTable headers={['Date', 'Orders', 'GMV']} rows={analytics.data.dailySales.map((item) => [item.label, String(item.orders), formatCurrency(item.value)])} /></CardContent></Card><Card><CardHeader><CardTitle>Top products</CardTitle></CardHeader><CardContent style={{ padding: 0 }}><SimpleTable headers={['Product', 'Units', 'Sales']} rows={analytics.data.topProducts.map((item) => [item.name, String(item.quantity), formatCurrency(item.sales)])} /></CardContent></Card><Card><CardHeader><CardTitle>Known limitations</CardTitle></CardHeader><CardContent><ul style={{ margin: 0, paddingLeft: 18 }}>{analytics.data.limitations.map((item) => <li key={item} className="text-sm text-muted">{item}</li>)}</ul></CardContent></Card></div>
      </>}
    </>}
  </>
}

function Metric({ title, value, icon, detail }: { title: string; value: string | number; icon: ReactNode; detail: string }) {
  return <Card><CardContent><div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--muted-foreground)' }}><span>{title}</span>{icon}</div><div style={{ fontSize: '1.7rem', fontWeight: 700, marginTop: 12 }}>{value}</div><div className="text-sm text-muted">{detail}</div></CardContent></Card>
}

function SimpleTable({ headers, rows }: { headers: string[]; rows: string[][] }) {
  return <div style={{ overflowX: 'auto' }}><table className="table"><thead><tr>{headers.map((header) => <th key={header}>{header}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row, index) => <tr key={index}>{row.map((cell, cellIndex) => <td key={cellIndex}>{cellIndex === row.length - 1 && ['pending', 'processing', 'shipped', 'delivered', 'cancelled'].includes(cell) ? <Badge>{cell}</Badge> : cell}</td>)}</tr>) : <tr><td colSpan={headers.length} className="text-muted">No records available.</td></tr>}</tbody></table></div>
}
