import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Package, ShoppingCart, TrendingUp, Users } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/Feedback'
import { Skeleton } from '@/components/ui/Skeleton'
import { Badge } from '@/components/ui/Badge'
import { formatCurrency, formatDate } from '@/lib/format'
import { queryKeys } from '@/lib/query-keys'
import { dashboardService } from './dashboard.service'

export function DashboardPage() {
  const overview = useQuery({ queryKey: queryKeys.dashboard.overview, queryFn: dashboardService.getOverview })
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
    </>}
  </>
}

function Metric({ title, value, icon, detail }: { title: string; value: string | number; icon: ReactNode; detail: string }) {
  return <Card><CardContent><div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--muted-foreground)' }}><span>{title}</span>{icon}</div><div style={{ fontSize: '1.7rem', fontWeight: 700, marginTop: 12 }}>{value}</div><div className="text-sm text-muted">{detail}</div></CardContent></Card>
}

function SimpleTable({ headers, rows }: { headers: string[]; rows: string[][] }) {
  return <div style={{ overflowX: 'auto' }}><table className="table"><thead><tr>{headers.map((header) => <th key={header}>{header}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row, index) => <tr key={index}>{row.map((cell, cellIndex) => <td key={cellIndex}>{cellIndex === row.length - 1 && ['pending', 'processing', 'shipped', 'delivered', 'cancelled'].includes(cell) ? <Badge>{cell}</Badge> : cell}</td>)}</tr>) : <tr><td colSpan={headers.length} className="text-muted">No records available.</td></tr>}</tbody></table></div>
}
