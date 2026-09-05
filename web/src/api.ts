let csrf = ''
let lang = 'en'

export function setCsrf(token: string) {
  csrf = token
}

export function setLang(locale: string) {
  lang = locale === 'zh' ? 'zh' : 'en'
}

export class ApiError extends Error {
  status: number
  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

export async function request<T = any>(path: string, options: { method?: string; body?: any } = {}): Promise<T> {
  const method = options.method || 'GET'
  const headers: Record<string, string> = { 'Content-Type': 'application/json', 'Accept-Language': lang }
  if (!['GET', 'HEAD'].includes(method)) {
    headers['X-CSRF-Token'] = csrf
  }
  const res = await fetch('/api' + path, {
    method,
    headers,
    credentials: 'same-origin',
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    throw new ApiError(data.error || 'HTTP ' + res.status, res.status)
  }
  return data as T
}

export function fmtBytes(n: number): string {
  if (!n || n <= 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let i = 0
  let v = n
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024
    i++
  }
  return v.toFixed(i === 0 ? 0 : 1) + ' ' + units[i]
}

export function fmtPct(free: number, total: number): string {
  if (!total) return '—'
  return Math.round(((total - free) / total) * 100) + '%'
}
