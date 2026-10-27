import { useEffect, useState } from 'react'
import { Download, Mail, RefreshCw } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { SearchInput } from '@/components/forms/SearchInput'
import { Select } from '@/components/ui/Input'
import { EmptyState, ErrorState } from '@/components/ui/Feedback'
import { Skeleton } from '@/components/ui/Skeleton'
import { formatDate } from '@/lib/format'
import { getUserMessage } from '@/lib/errors'
import { appConfig } from '@/config/app'
import { newsletterService, type NewsletterStats } from '../newsletter.service'

const emptyStats: NewsletterStats = { total: 0, confirmed: 0, pending: 0, unsubscribed: 0 }

export function NewsletterPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [rows, setRows] = useState<Awaited<ReturnType<typeof newsletterService.list>> | null>(null)
  const [stats, setStats] = useState<NewsletterStats>(emptyStats)
  const [error, setError] = useState<unknown>(null)
  const [loading, setLoading] = useState(true)

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const [list, overview] = await Promise.all([
        newsletterService.list({ page, pageSize: 10, search: search || undefined, status: status || undefined }),
        newsletterService.overview(),
      ])
      setRows(list)
      setStats(overview.data)
    } catch (loadError) {
      setError(loadError)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { void load() }, [page, search, status])

  const exportCsv = () => {
    window.open(`${appConfig.apiBaseUrl.replace(/\/$/, '')}/admin/newsletter/subscribers/export`, '_blank', 'noopener,noreferrer')
  }

  return (
    <>
      <PageHeader
        title="Newsletter Subscribers"
        description="Review subscription consent and confirmation status."
        actions={<Button variant="secondary" onClick={exportCsv}><Download size={15} aria-hidden="true" /> Export CSV</Button>}
      />

      <div className="stats-grid" aria-label="Newsletter totals">
        {([['Total', stats.total], ['Confirmed', stats.confirmed], ['Pending', stats.pending], ['Unsubscribed', stats.unsubscribed]] as const).map(([label, value]) => (
          <div className="stat-card" key={label}><span className="stat-card__label">{label}</span><strong className="stat-card__value">{value}</strong></div>
        ))}
      </div>

      <div className="table__toolbar">
        <SearchInput value={search} onChange={(value) => { setPage(1); setSearch(value) }} placeholder="Search email…" label="Search newsletter subscribers" />
        <Select aria-label="Filter by subscription status" options={[{ value: '', label: 'All statuses' }, { value: 'confirmed', label: 'Confirmed' }, { value: 'pending', label: 'Pending' }, { value: 'unsubscribed', label: 'Unsubscribed' }]} value={status} onChange={(event) => { setPage(1); setStatus(event.target.value) }} style={{ width: 170 }} />
        <Button variant="ghost" size="sm" onClick={() => void load()}><RefreshCw size={14} aria-hidden="true" /> Refresh</Button>
      </div>

      {error ? <ErrorState message={getUserMessage(error)} onRetry={() => void load()} /> : (
        <div className="table-container">
          <table className="table" aria-label="Newsletter subscribers">
            <thead><tr><th>Email</th><th>Status</th><th>Source</th><th>Consent date</th><th>Confirmed/unsubscribed</th></tr></thead>
            <tbody>
              {loading && [1, 2, 3].map((item) => <tr key={item}><td colSpan={5}><Skeleton style={{ height: 18, width: '70%' }} /></td></tr>)}
              {!loading && rows?.data.length === 0 && <tr><td colSpan={5}><EmptyState icon={<Mail size={18} />} title="No subscribers found" description="New footer subscriptions will appear here." compact /></td></tr>}
              {!loading && rows?.data.map((subscriber) => <tr key={subscriber.id}><td>{subscriber.email}</td><td><span className={`badge badge--${subscriber.status}`}>{subscriber.status}</span></td><td>{subscriber.source}</td><td>{formatDate(subscriber.consentAt)}</td><td>{subscriber.confirmedAt || subscriber.unsubscribedAt ? formatDate(subscriber.confirmedAt || subscriber.unsubscribedAt || '') : '—'}</td></tr>)}
            </tbody>
          </table>
          {rows && rows.meta.totalPages > 1 && <div className="pagination"><Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span>Page {page} of {rows.meta.totalPages}</span><Button variant="secondary" size="sm" disabled={page >= rows.meta.totalPages} onClick={() => setPage(page + 1)}>Next</Button></div>}
        </div>
      )}
    </>
  )
}
