import { useEffect, useState } from 'react'
import { closeTablet, nuiCallback } from './api'
import Overview from './views/Overview'
import Placeholder from './views/Placeholder'

type Labels = {
  title: string
  placeholder: string
  overview: string
  servicing: string
  invoices: string
  register: string
  settings: string
}

type OpenMessage = {
  action: 'open' | 'close'
  locale?: string
  labels?: Labels
}

const defaultLabels: Labels = {
  title: 'Workshop',
  placeholder: 'Scaffold',
  overview: 'Overview',
  servicing: 'Servicing',
  invoices: 'Invoices',
  register: 'Register',
  settings: 'Settings',
}

type AppId = 'overview' | 'servicing' | 'invoices' | 'register' | 'settings'

export default function App() {
  const [visible, setVisible] = useState(false)
  const [labels, setLabels] = useState<Labels>(defaultLabels)
  const [app, setApp] = useState<AppId>('overview')

  useEffect(() => {
    void nuiCallback('ready')

    const onMessage = (event: MessageEvent<OpenMessage>) => {
      const data = event.data
      if (!data || typeof data !== 'object') return
      if (data.action === 'open') {
        if (data.labels) setLabels({ ...defaultLabels, ...data.labels })
        setApp('overview')
        setVisible(true)
      }
      if (data.action === 'close') {
        setVisible(false)
      }
    }

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && visible) {
        void closeTablet()
      }
    }

    window.addEventListener('message', onMessage)
    window.addEventListener('keydown', onKey)
    return () => {
      window.removeEventListener('message', onMessage)
      window.removeEventListener('keydown', onKey)
    }
  }, [visible])

  if (!visible) {
    return null
  }

  const nav: { id: AppId; label: string }[] = [
    { id: 'overview', label: labels.overview },
    { id: 'servicing', label: labels.servicing },
    { id: 'invoices', label: labels.invoices },
    { id: 'register', label: labels.register },
    { id: 'settings', label: labels.settings },
  ]

  return (
    <div className="shell">
      <header className="topbar">
        <div>
          <p className="brand">ef_mechanic</p>
          <h1>{labels.title}</h1>
        </div>
        <button type="button" className="ghost" onClick={() => void closeTablet()}>
          Esc
        </button>
      </header>

      <nav className="nav">
        {nav.map((item) => (
          <button
            key={item.id}
            type="button"
            className={app === item.id ? 'nav-item active' : 'nav-item'}
            onClick={() => setApp(item.id)}
          >
            {item.label}
          </button>
        ))}
      </nav>

      <main className="content">
        {app === 'overview' ? (
          <Overview title={labels.overview} hint={labels.placeholder} />
        ) : (
          <Placeholder title={nav.find((n) => n.id === app)?.label ?? ''} hint={labels.placeholder} />
        )}
      </main>
    </div>
  )
}
