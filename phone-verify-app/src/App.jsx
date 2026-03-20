import { useState, useCallback, useRef } from 'react'
import './App.css'

// API lives at same level as phone-verify: .../public/api/
function getApiBase() {
  if (import.meta.env.VITE_API_BASE) return import.meta.env.VITE_API_BASE
  if (import.meta.env.DEV) return 'http://localhost/printflow/public'
  // From current URL (e.g. .../phone-verify/ or .../phone-verify/index.html), strip to get .../public/
  const p = window.location.pathname
  const base = p.replace(/\/phone-verify(\/.*)?$/, '').replace(/\/$/, '') || '/printflow/public'
  return window.location.origin + base
}
const apiUrl = (path) => getApiBase() + path

function formatInput(value) {
  const digits = value.replace(/\D/g, '').slice(0, 12)
  if (digits.startsWith('63')) {
    const a = digits.slice(0, 3), b = digits.slice(3, 6), c = digits.slice(6, 10), d = digits.slice(10, 12)
    return '+' + a + (b ? ' ' + b : '') + (c ? ' ' + c : '') + (d ? d : '')
  }
  if (digits.startsWith('0') || digits.startsWith('9')) {
    const a = digits.slice(0, 3), b = digits.slice(3, 6), c = digits.slice(6, 11)
    return a + (b ? ' ' + b : '') + (c ? ' ' + c : '')
  }
  return digits
}

function validateFormat(number) {
  const raw = number.replace(/\s/g, '')
  const digits = raw.replace(/\D/g, '')
  if (digits.length === 0) return { valid: false, message: '' }
  // +63 9XX XXX XXXX or 09XX XXX XXXX
  if (/^\+?63\d{10}$/.test(raw) || /^63\d{10}$/.test(digits)) return { valid: true, message: '' }
  if (/^09\d{9}$/.test(raw) || (digits.length === 11 && digits.startsWith('09'))) return { valid: true, message: '' }
  if (digits.length === 10 && digits.startsWith('9')) return { valid: true, message: '' }
  if (digits.length < 10) return { valid: false, message: 'Number too short' }
  if (digits.length > 12) return { valid: false, message: 'Number too long' }
  if (!/^(\+?63|0?9)\d*$/.test(raw)) return { valid: false, message: 'Use +63 or 09 and digits only' }
  return { valid: false, message: 'Invalid format' }
}

function CheckIcon() {
  return (
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="M4 10l4 4 8-8" />
    </svg>
  )
}
function XIcon() {
  return (
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="M5 5l10 10M15 5l-10 10" />
    </svg>
  )
}

export default function App() {
  const [phone, setPhone] = useState('')
  const [apiResult, setApiResult] = useState(null)
  const [loading, setLoading] = useState(false)
  const [otpSent, setOtpSent] = useState(false)
  const [otpCode, setOtpCode] = useState(['', '', '', '', '', ''])
  const [otpVerified, setOtpVerified] = useState(false)
  const [otpChecking, setOtpChecking] = useState(false)
  const otpInputRefs = useRef([])

  const formatCheck = validateFormat(phone)
  const canVerify = formatCheck.valid && phone.replace(/\D/g, '').length >= 10

  const handlePhoneChange = (e) => {
    const v = e.target.value
    const digits = v.replace(/\D/g, '')
    if (digits.length > 12) return
    if (digits && !/^(\+?63|0?9)/.test(digits)) return
    setPhone(formatInput(v))
    setApiResult(null)
  }

  const verifyNumber = useCallback(async () => {
    if (!canVerify) return
    setLoading(true)
    setApiResult(null)
    try {
      const num = phone.replace(/\D/g, '')
      const query = num.startsWith('63') ? num : '63' + num.replace(/^0/, '')
      const url = apiUrl('/api/phone_verify.php') + `?number=${encodeURIComponent(query)}`
      const res = await fetch(url)
      let data
      try {
        data = await res.json()
      } catch {
        data = { valid: false, error: res.ok ? 'Invalid response.' : `Server error (${res.status}).` }
      }
      if (!res.ok) {
        data = { valid: false, error: data.error || `Request failed (${res.status}).` }
      }
      setApiResult(data)
    } catch (err) {
      setApiResult({ valid: false, error: 'Network error. Please try again.' })
    } finally {
      setLoading(false)
    }
  }, [phone, canVerify])

  const sendOtp = useCallback(async () => {
    if (!apiResult?.valid) return
    setLoading(true)
    try {
      const num = phone.replace(/\D/g, '')
      const query = num.startsWith('63') ? num : '63' + num.replace(/^0/, '')
      const res = await fetch(apiUrl('/api/phone_verify_otp_send.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ number: query }),
      })
      const data = await res.json()
      if (data.success) {
        setOtpSent(true)
        if (data.debug_code) {
          const arr = data.debug_code.toString().split('').slice(0, 6)
          setOtpCode([...arr, ...Array(6 - arr.length).fill('')])
        }
      } else {
        setApiResult({ ...apiResult, error: data.error })
      }
    } catch (err) {
      setApiResult({ ...apiResult, error: 'Failed to send OTP.' })
    } finally {
      setLoading(false)
    }
  }, [apiResult, phone])

  const handleOtpChange = (i, v) => {
    if (v.length > 1) {
      const arr = v.replace(/\D/g, '').slice(0, 6).split('')
      const next = [...otpCode]
      arr.forEach((a, j) => { if (i + j < 6) next[i + j] = a })
      setOtpCode(next)
      const n = Math.min(i + arr.length, 5)
      otpInputRefs.current[n]?.focus()
      return
    }
    const next = [...otpCode]
    next[i] = v.replace(/\D/g, '').slice(0, 1)
    setOtpCode(next)
    if (v && i < 5) otpInputRefs.current[i + 1]?.focus()
    else if (!v && i > 0) otpInputRefs.current[i - 1]?.focus()
  }

  const verifyOtp = useCallback(async () => {
    const code = otpCode.join('')
    if (code.length !== 6) return
    setOtpChecking(true)
    try {
      const num = phone.replace(/\D/g, '')
      const query = num.startsWith('63') ? num : '63' + num.replace(/^0/, '')
      const res = await fetch(apiUrl('/api/phone_verify_otp_check.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ number: query, code }),
      })
      const data = await res.json()
      if (data.valid) setOtpVerified(true)
      else setApiResult({ ...apiResult, error: data.error })
    } catch (err) {
      setApiResult({ ...apiResult, error: 'Verification failed.' })
    } finally {
      setOtpChecking(false)
    }
  }, [otpCode, phone, apiResult])

  const resetOtp = () => {
    setOtpSent(false)
    setOtpCode(['', '', '', '', '', ''])
    setOtpVerified(false)
  }

  const canSubmit = otpVerified || (apiResult?.valid && !otpSent)

  return (
    <div className="app">
      <header className="header">
        <h1>Phone Verification</h1>
        <p>Philippine mobile numbers only (+63)</p>
      </header>

      <main className="card">
        {!otpVerified ? (
          <>
            <div className="field">
              <label htmlFor="phone">Mobile number</label>
              <div className={`input-wrap ${apiResult?.valid ? 'valid' : ''} ${apiResult?.error ? 'invalid' : ''}`}>
                <input
                  id="phone"
                  type="tel"
                  placeholder="+63 9XX XXX XXXX or 09XX XXX XXXX"
                  value={phone}
                  onChange={handlePhoneChange}
                  disabled={otpSent}
                  autoComplete="tel"
                />
                {apiResult?.valid && (
                  <span className="icon success" aria-hidden><CheckIcon /></span>
                )}
                {apiResult?.error && !apiResult?.valid && (
                  <span className="icon error" aria-hidden><XIcon /></span>
                )}
              </div>
              {formatCheck.message && <span className="hint error">{formatCheck.message}</span>}
              {apiResult?.error && <span className="hint error">{apiResult.error}</span>}
              {apiResult?.valid && apiResult?.carrier && (
                <span className="hint success">
                  {apiResult.carrier}{apiResult.location ? ` • ${apiResult.location}` : ''}
                </span>
              )}
            </div>

            {!otpSent ? (
              <>
                <button
                  className="btn btn-primary"
                  onClick={verifyNumber}
                  disabled={!canVerify || loading}
                >
                  {loading ? 'Verifying…' : 'Verify number'}
                </button>

                {apiResult?.valid && (
                  <button
                    className="btn btn-secondary"
                    onClick={sendOtp}
                    disabled={loading}
                  >
                    {loading ? 'Sending…' : 'Send OTP (simulation)'}
                  </button>
                )}
              </>
            ) : (
              <div className="otp-section">
                <label>Enter 6-digit code</label>
                <div className="otp-inputs">
                  {otpCode.map((d, i) => (
                    <input
                      key={i}
                      ref={(el) => otpInputRefs.current[i] = el}
                      type="text"
                      inputMode="numeric"
                      maxLength={6}
                      value={d}
                      onChange={(e) => handleOtpChange(i, e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Backspace' && !d && i > 0) otpInputRefs.current[i - 1]?.focus()
                      }}
                    />
                  ))}
                </div>
                <button
                  className="btn btn-primary"
                  onClick={verifyOtp}
                  disabled={otpCode.join('').length !== 6 || otpChecking}
                >
                  {otpChecking ? 'Verifying…' : 'Verify OTP'}
                </button>
                <button className="btn btn-ghost" onClick={resetOtp}>
                  Change number
                </button>
              </div>
            )}
          </>
        ) : (
          <div className="success-block">
            <span className="icon success big"><CheckIcon /></span>
            <h2>Phone verified</h2>
            <p>{apiResult?.international_format || phone}</p>
            <p className="carrier">{apiResult?.carrier}</p>
          </div>
        )}

        <button
          className="btn btn-submit"
          disabled={!canSubmit}
          style={{ marginTop: '1rem' }}
        >
          {canSubmit ? 'Continue to registration' : 'Verify phone to continue'}
        </button>
      </main>

      <footer className="footer">
        <p>Format: +63 9XX XXX XXXX or 09XX XXX XXXX</p>
      </footer>
    </div>
  )
}
