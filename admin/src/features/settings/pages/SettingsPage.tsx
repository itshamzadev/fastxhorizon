import { useEffect, useState } from 'react'
import { Monitor, Moon, Sun } from 'lucide-react'
import type { AuthUser } from '@/models/User'
import { updateProfileSchema } from '@/models/schemas'
import { PageHeader } from '@/components/layout/PageHeader'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Field, Input } from '@/components/ui/Input'
import { Alert } from '@/components/ui/Feedback'
import { useAuth } from '@/app/providers/AuthProvider'
import { useTheme } from '@/app/providers/ThemeProvider'
import type { ThemePreference } from '@/app/providers/ThemeProvider'
import { authService } from '@/services/auth/auth.service'
import { getUserMessage } from '@/lib/errors'
import { useToast } from '@/components/feedback/ToastProvider'
import { appConfig } from '@/config/app'
import { useForm, validate } from '@/hooks/use-form'
import { settingsService, DEFAULT_ANNOUNCEMENT, type AnnouncementSettings } from '../settings.service'

const THEME_OPTIONS: ReadonlyArray<{
  value: ThemePreference
  label: string
  previewClass: string
  icon: typeof Sun
}> = [
  {
    value: 'light',
    label: 'Light',
    previewClass: 'theme-option__preview--light',
    icon: Sun,
  },
  {
    value: 'dark',
    label: 'Dark',
    previewClass: 'theme-option__preview--dark',
    icon: Moon,
  },
  {
    value: 'system',
    label: 'System',
    previewClass: 'theme-option__preview--system',
    icon: Monitor,
  },
]

interface ProfileValues {
  name: string
  email: string
}

/**
 * Account settings: profile details (persisted through the auth API) and
 * appearance preferences. A template example of a simple settings page.
 */
export function SettingsPage() {
  const { user } = useAuth()

  return (
    <>
      <PageHeader
        title="Settings"
        description="Manage your profile and application preferences."
      />

      <div className="settings-layout">
        {user ? <ProfileCard user={user} /> : null}

        <AnnouncementCard />

        {user?.role === 'super_admin' ? <StoreOperationsCard /> : null}

        <AppearanceCard />

        <Card>
          <CardHeader>
            <div>
              <CardTitle>About</CardTitle>
              <CardDescription>Template information.</CardDescription>
            </div>
          </CardHeader>
          <CardContent style={{ paddingTop: 8 }}>
            <p className="text-sm text-muted">
              {appConfig.name} v{appConfig.version} — a React admin template with a mock
              API, theming and role-based access. Swap the mock services for your backend
              to ship.
            </p>
          </CardContent>
        </Card>
      </div>
    </>
  )
}

function AnnouncementCard() {
  const toast = useToast()
  const [settings, setSettings] = useState<AnnouncementSettings>({ enabled: true, message: DEFAULT_ANNOUNCEMENT })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    void settingsService.getAnnouncement()
      .then(setSettings)
      .catch((reason) => setError(getUserMessage(reason)))
      .finally(() => setLoading(false))
  }, [])

  const save = async () => {
    const message = settings.message.trim()
    if (!message || message.length > 180 || /[<>]/.test(message)) {
      setError('Use plain text between 1 and 180 characters.')
      return
    }
    setSaving(true)
    setError(null)
    try {
      const saved = await settingsService.updateAnnouncement({ enabled: settings.enabled, message })
      setSettings(saved)
      toast.success('Announcement settings saved')
    } catch (reason) {
      setError(getUserMessage(reason))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <div>
          <CardTitle>Storefront announcement</CardTitle>
          <CardDescription>Control the message shown below the customer website header.</CardDescription>
        </div>
      </CardHeader>
      <CardContent style={{ paddingTop: 8 }}>
        {error && <div style={{ marginBottom: 14 }}><Alert tone="destructive">{error}</Alert></div>}
        <Field id="announcement-message" label="Announcement message" required error={undefined}>
          <textarea
            id="announcement-message"
            className="input textarea"
            maxLength={180}
            rows={3}
            disabled={loading || saving}
            value={settings.message}
            onChange={(event) => setSettings((current) => ({ ...current, message: event.target.value }))}
          />
        </Field>
        <label className="settings-row" htmlFor="announcement-enabled" style={{ marginTop: 14, cursor: 'pointer' }}>
          <span>
            <strong>Show announcement bar</strong>
            <span className="text-sm text-muted" style={{ display: 'block' }}>Customers see the bar immediately after refreshing the storefront.</span>
          </span>
          <input id="announcement-enabled" type="checkbox" checked={settings.enabled} disabled={loading || saving} onChange={(event) => setSettings((current) => ({ ...current, enabled: event.target.checked }))} />
        </label>
      </CardContent>
      <CardFooter style={{ justifyContent: 'flex-end' }}>
        <Button variant="primary" onClick={() => void save()} isLoading={saving} disabled={loading || saving}>Save announcement</Button>
      </CardFooter>
    </Card>
  )
}

function StoreOperationsCard() {
  const toast = useToast()
  const [settings, setSettings] = useState<import('../settings.service').StoreOperationsSettings | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [reason, setReason] = useState('')

  useEffect(() => {
    void settingsService.getStoreOperations().then(setSettings).catch((value) => setError(getUserMessage(value))).finally(() => setLoading(false))
  }, [])

  const save = async () => {
    if (!settings) return
    const action = settings.onlineOrderingEnabled ? 'close new orders' : 'open customer orders'
    if (!window.confirm(`Confirm you want to ${action}? Existing orders remain available for management.`)) return
    setSaving(true); setError(null)
    try {
      const saved = await settingsService.updateStoreOperations({ onlineOrderingEnabled: settings.onlineOrderingEnabled, message: settings.message, reason })
      setSettings(saved); setReason(''); toast.success(saved.onlineOrderingEnabled ? 'Online ordering is now open' : 'Online ordering is now closed')
    } catch (value) { setError(getUserMessage(value)) } finally { setSaving(false) }
  }

  return <Card>
    <CardHeader><div><CardTitle>Online ordering</CardTitle><CardDescription>Master control for new customer orders. Payments remain separately controlled.</CardDescription></div></CardHeader>
    <CardContent style={{ paddingTop: 8 }}>
      {error && <div style={{ marginBottom: 14 }}><Alert tone="destructive">{error}</Alert></div>}
      <label className="settings-row" htmlFor="online-ordering-enabled" style={{ cursor: loading || saving ? 'default' : 'pointer' }}>
        <span><strong>{settings?.onlineOrderingEnabled ? 'ON — Orders Open' : 'OFF — Orders Closed'}</strong><span className="text-sm text-muted" style={{ display: 'block' }}>Only a Super Admin can change this persistent setting.</span></span>
        <input id="online-ordering-enabled" type="checkbox" checked={settings?.onlineOrderingEnabled ?? false} disabled={loading || saving} onChange={(event) => setSettings((current) => current ? ({ ...current, onlineOrderingEnabled: event.target.checked }) : current)} />
      </label>
      <Field id="ordering-message" label="Customer closure message" hint="Used when orders are closed."><Input id="ordering-message" maxLength={240} disabled={!settings || loading || saving} value={settings?.message ?? ''} onChange={(event) => setSettings((current) => current ? ({ ...current, message: event.target.value }) : current)} /></Field>
      <Field id="ordering-reason" label="Reason (optional)"><Input id="ordering-reason" maxLength={500} disabled={!settings || loading || saving} value={reason} onChange={(event) => setReason(event.target.value)} /></Field>
      {settings?.updatedAt && <p className="text-sm text-muted">Last changed: {new Date(settings.updatedAt).toLocaleString()} {settings.updatedBy ? `by ${settings.updatedBy.email}` : ''}</p>}
    </CardContent>
    <CardFooter style={{ justifyContent: 'flex-end' }}><Button variant="primary" onClick={() => void save()} isLoading={saving} disabled={!settings || loading || saving}>Save order status</Button></CardFooter>
  </Card>
}

function ProfileCard({ user }: { user: AuthUser }) {
  const { setUser } = useAuth()
  const toast = useToast()
  const [serverError, setServerError] = useState<string | null>(null)

  const form = useForm<ProfileValues, Record<string, string>>({
    name: user?.name ?? '',
    email: user?.email ?? '',
  })
  const isDirty =
    form.values.name !== (user?.name ?? '') || form.values.email !== (user?.email ?? '')

  const handleSubmit = async () => {
    setServerError(null)
    form.setErrors({})

    const result = validate(updateProfileSchema, form.values)
    if (!result.ok) {
      form.setErrors(result.fieldErrors)
      return
    }

    form.setSubmitting(true)
    try {
      const updated = await authService.updateProfile(result.data)
      setUser(updated)
      toast.success('Profile updated')
    } catch (error) {
      applyServerFields((error as { fields?: Record<string, string> }).fields)
      if ((error as { fields?: Record<string, string> }).fields === undefined) {
        setServerError(getUserMessage(error))
      }
    } finally {
      form.setSubmitting(false)
    }
  }

  const applyServerFields = (fields: Record<string, string> | undefined) => {
    if (fields && Object.keys(fields).length > 0) {
      form.setErrors(fields as Partial<Record<keyof ProfileValues, string>>)
    }
  }

  return (
    <Card>
      <CardHeader>
        <div>
          <CardTitle>Profile</CardTitle>
          <CardDescription>
            How your account appears across the workspace.
          </CardDescription>
        </div>
      </CardHeader>

      <CardContent style={{ paddingTop: 8 }}>
        {serverError && (
          <div style={{ marginBottom: 14 }}>
            <Alert tone="destructive">{serverError}</Alert>
          </div>
        )}

        <Field id="profile-name" label="Full name" required error={form.errors.name}>
          <Input
            id="profile-name"
            autoComplete="name"
            value={form.values.name}
            invalid={Boolean(form.errors.name)}
            onChange={(event) => {
              form.setField('name', event.target.value)
              form.clearError('name')
            }}
          />
        </Field>

        <Field id="profile-email" label="Email" required error={form.errors.email}>
          <Input
            id="profile-email"
            type="email"
            autoComplete="email"
            value={form.values.email}
            invalid={Boolean(form.errors.email)}
            onChange={(event) => {
              form.setField('email', event.target.value)
              form.clearError('email')
            }}
          />
        </Field>
      </CardContent>

      <CardFooter style={{ justifyContent: 'flex-end' }}>
        <Button
          variant="primary"
          onClick={handleSubmit}
          isLoading={form.submitting}
          disabled={!isDirty || form.submitting}
        >
          Save changes
        </Button>
      </CardFooter>
    </Card>
  )
}

function AppearanceCard() {
  const { preference, resolvedTheme, setPreference } = useTheme()

  return (
    <Card>
      <CardHeader>
        <div>
          <CardTitle>Appearance</CardTitle>
          <CardDescription>
            Currently rendering in {resolvedTheme} mode. "System" follows your OS setting.
          </CardDescription>
        </div>
      </CardHeader>
      <CardContent className="settings-section__body" style={{ paddingTop: 8 }}>
        <span className="text-sm text-muted">Theme</span>
        <div className="theme-options" role="radiogroup" aria-label="Theme">
          {THEME_OPTIONS.map((option) => {
            const Icon = option.icon
            const selected = preference === option.value
            return (
              <button
                key={option.value}
                type="button"
                role="radio"
                aria-checked={selected}
                className={`theme-option ${selected ? 'theme-option--selected' : ''}`}
                onClick={() => setPreference(option.value)}
              >
                <span
                  className={`theme-option__preview ${option.previewClass}`}
                  aria-hidden="true"
                />
                <Icon size={14} aria-hidden="true" />
                {option.label}
              </button>
            )
          })}
        </div>
      </CardContent>
    </Card>
  )
}
