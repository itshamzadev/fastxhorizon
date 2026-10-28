import { useEffect, useState } from 'react'
import { History } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { TableRoot, Td, Th, THead, Tr } from '@/components/ui/Table'
import { EmptyState, ErrorState } from '@/components/ui/Feedback'
import { Badge } from '@/components/ui/Badge'
import { getUserMessage } from '@/lib/errors'
import { importService, type ImportHistory } from '../product-import.service'

export function ImportHistoryPage() { const [items, setItems] = useState<ImportHistory[]>([]); const [error, setError] = useState<unknown>(null); const [loading, setLoading] = useState(true); const load = () => { setLoading(true); importService.history().then((result) => setItems(result.data)).catch(setError).finally(() => setLoading(false)) }; useEffect(load, [])
  return <><PageHeader title="Import History" description="Auditable supplier catalog import outcomes and validation counts." />{error ? <ErrorState message={getUserMessage(error)} onRetry={load} /> : <TableRoot caption="Supplier catalog import history"><THead><tr><Th>Filename</Th><Th>Supplier</Th><Th>Products</Th><Th>Outcome</Th><Th>Status</Th><Th>Date</Th></tr></THead><tbody>{loading && <tr><Td colSpan={6}>Loading history…</Td></tr>}{!loading && items.length === 0 && <tr><td colSpan={6}><EmptyState icon={<History size={18} />} title="No imports yet" description="Confirmed imports will appear here." /></td></tr>}{items.map((item) => <Tr key={item.id}><Td>{item.filename}</Td><Td>{item.supplier}</Td><Td>{item.summary.uniqueProducts}</Td><Td>{item.summary.newProducts} processed / {item.summary.skippedProducts} skipped / {item.summary.failedProducts} failed</Td><Td><Badge tone={item.status === 'COMPLETED' ? 'success' : item.status === 'FAILED' ? 'destructive' : 'warning'}>{item.status}</Badge></Td><Td>{new Date(item.createdAt).toLocaleString()}</Td></Tr>)}</tbody></TableRoot>}</> }
