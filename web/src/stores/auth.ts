import { defineStore } from 'pinia'
import { request, setCsrf } from '../api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as { username: string; local?: boolean } | null,
    csrf: '',
    local: false,
    ready: false,
  }),
  actions: {
    async init() {
      try {
        const d = await request('/auth/me')
        this.user = d.user
        this.csrf = d.csrf
        this.local = d.local
        setCsrf(d.csrf)
      } catch {
        this.user = null
      } finally {
        this.ready = true
      }
    },
    async login(username: string, password: string) {
      const d = await request('/auth/login', { method: 'POST', body: { username, password } })
      this.user = d.user
    },
    async initAccount(token: string, username: string, password: string) {
      const d = await request('/auth/init', { method: 'POST', body: { token, username, password } })
      this.user = d.user
    },
    async logout() {
      try {
        await request('/auth/logout', { method: 'POST' })
      } finally {
        this.user = null
      }
    },
    async changePassword(oldP: string, newP: string) {
      await request('/auth/password', { method: 'POST', body: { old: oldP, new: newP } })
    },
  },
})
