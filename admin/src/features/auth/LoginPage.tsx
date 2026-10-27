import { useState, type FormEvent } from 'react'
import { Eye, EyeOff, ShieldCheck } from 'lucide-react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '@/app/providers/AuthProvider'
import { authService, type MfaEnrollment } from '@/services/auth/auth.service'
import { getUserMessage } from '@/lib/errors'
import { loginSchema } from '@/models/schemas'
import { useForm, validate } from '@/hooks/use-form'
import { Button } from '@/components/ui/Button'
import { Field, Input } from '@/components/ui/Input'

interface LoginValues {
  email: string
  password: string
}

export function LoginPage() {
  const { login, completeLogin } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [formError, setFormError] = useState<string | null>(null)
  const [showPassword, setShowPassword] = useState(false)
  const [mfaRequired, setMfaRequired] = useState(false)
  const [mfaEnrollment, setMfaEnrollment] = useState<MfaEnrollment | null>(null)
  const [mfaCode, setMfaCode] = useState('')
  const [useRecoveryCode, setUseRecoveryCode] = useState(false)

  const form = useForm<LoginValues, { email?: string; password?: string }>({
    email: '',
    password: '',
  })

  const finishLogin = (user: Parameters<typeof completeLogin>[0]) => {
    completeLogin(user)
    const target = (location.state as { from?: string } | null)?.from ?? '/'
    navigate(target, { replace: true })
  }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setFormError(null)
    const result = validate(loginSchema, form.values)
    if (!result.ok) {
      form.setErrors(result.fieldErrors)
      return
    }

    form.setSubmitting(true)
    try {
      const response = await login(result.data)
      if (response.mfaRequired) {
        setMfaRequired(true)
        if (response.enrollmentRequired) {
          setMfaEnrollment(await authService.enrollMfa())
        }
        return
      }
      if (response.user) finishLogin(response.user)
    } catch (error) {
      setFormError(getUserMessage(error))
    } finally {
      form.setSubmitting(false)
    }
  }

  const handleMfaSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setFormError(null)
    if (!mfaCode.trim()) {
      setFormError('Enter the verification code from your authenticator.')
      return
    }
    try {
      const response = useRecoveryCode
        ? await authService.verifyRecoveryCode(mfaCode)
        : await authService.verifyMfa(mfaCode)
      finishLogin(response.user)
    } catch (error) {
      setFormError(getUserMessage(error))
    }
  }

  return (
    <div className="login-page">
      <div className="login-page__left">
        <div className="login-card">
          <div className="login-card__brand">
            <span className="login-card__mark" aria-hidden="true">F</span>
            <span className="login-card__text-logo" aria-label="FASTXHORIZON Admin">
              <span className="login-card__text-logo-brand">FASTXHORIZON</span>{' '}
              <span className="login-card__text-logo-sub">Admin</span>
            </span>
          </div>

          {!mfaRequired && (
            <>
              <h1 className="login-card__title">Administrator sign in</h1>
              <p className="login-card__subtitle">Sign in to the secure FASTXHORIZON control panel.</p>
              {formError && <div role="alert" className="alert alert--destructive login-error">{formError}</div>}
              <form onSubmit={handleSubmit} noValidate className="login-form">
                <Field id="email" label="Admin email" error={form.errors.email} required>
                  <Input id="email" name="email" type="email" autoComplete="username" placeholder="admin@company.com" value={form.values.email} invalid={Boolean(form.errors.email)} onChange={(event) => { form.setField('email', event.target.value); form.clearError('email') }} />
                </Field>
                <Field id="password" label="Password" error={form.errors.password} required>
                  <span className="login-password">
                    <Input id="password" name="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" placeholder="Enter your password" value={form.values.password} invalid={Boolean(form.errors.password)} onChange={(event) => { form.setField('password', event.target.value); form.clearError('password') }} />
                    <button type="button" className="icon-btn login-password__toggle" aria-label={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword((visible) => !visible)}>
                      {showPassword ? <EyeOff size={15} aria-hidden="true" /> : <Eye size={15} aria-hidden="true" />}
                    </button>
                  </span>
                </Field>
                <Button type="submit" variant="primary" isLoading={form.submitting}>Sign in</Button>
              </form>
            </>
          )}

          {mfaRequired && (
            <>
              <h1 className="login-card__title">Verify your authenticator</h1>
              <p className="login-card__subtitle">Scan the QR code with Google Authenticator, then enter the six-digit code.</p>
              {formError && <div role="alert" className="alert alert--destructive login-error">{formError}</div>}
              <form onSubmit={handleMfaSubmit} className="login-form">
                {mfaEnrollment && <img src={mfaEnrollment.qrCode} alt="MFA enrollment QR code" style={{ width: 180, height: 180, alignSelf: 'center' }} />}
                <Field id="mfa-code" label={useRecoveryCode ? 'Recovery code' : 'Authenticator code'} required>
                  <Input id="mfa-code" inputMode={useRecoveryCode ? 'text' : 'numeric'} autoComplete="one-time-code" value={mfaCode} placeholder={useRecoveryCode ? 'FXH-...' : '000000'} onChange={(event) => setMfaCode(event.target.value)} />
                </Field>
                <Button type="submit" variant="primary">Verify and continue</Button>
                <button type="button" className="login-forgot" onClick={() => setUseRecoveryCode((value) => !value)}>
                  {useRecoveryCode ? 'Use authenticator code' : 'Use a recovery code'}
                </button>
              </form>
              {mfaEnrollment && <p className="login-reset-hint">Save these recovery codes securely: {mfaEnrollment.recoveryCodes.join(' · ')}</p>}
            </>
          )}
        </div>
      </div>
      <aside className="login-page__right" aria-label="FASTXHORIZON security">
        <div className="login-page__panel">
          <p className="login-page__eyebrow"><ShieldCheck size={16} /> Secure administration</p>
          <h2 className="login-page__overlay-title">Manage FASTXHORIZON with confidence.</h2>
          <p className="login-page__overlay-desc">Administrator access is protected by server-managed sessions and production MFA.</p>
        </div>
      </aside>
    </div>
  )
}
