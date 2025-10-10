// System mapping helper for frontend
// This mirrors the SystemMappingService from the backend

export interface SystemInfo {
  name: string
  shortName: string
  badgeColor: string
  indicatorColor: string
  gradientClasses: {
    from: string
    to: string
    border: string
  }
}

export const systemMapping: Record<string, SystemInfo> = {
  'Collme Beauty': {
    name: 'Collme Beauty',
    shortName: 'Beauty',
    badgeColor: 'bg-yellow-200 text-yellow-900 border border-yellow-300',
    indicatorColor: 'bg-yellow-500',
    gradientClasses: {
      from: 'from-yellow-400',
      to: 'to-yellow-500',
      border: 'border-yellow-400'
    }
  },
  'Collme Pro': {
    name: 'Collme Pro',
    shortName: 'Pro',
    badgeColor: 'bg-blue-200 text-blue-900 border border-blue-300',
    indicatorColor: 'bg-blue-500',
    gradientClasses: {
      from: 'from-blue-600',
      to: 'to-blue-700',
      border: 'border-blue-500'
    }
  },
  'Atacadista': {
    name: 'Atacadista',
    shortName: 'Atacadista',
    badgeColor: 'bg-blue-200 text-blue-900 border border-blue-300',
    indicatorColor: 'bg-blue-500',
    gradientClasses: {
      from: 'from-blue-600',
      to: 'to-blue-700',
      border: 'border-blue-500'
    }
  },
  'TikTok': {
    name: 'TikTok',
    shortName: 'TikTok',
    badgeColor: 'bg-slate-200 text-slate-900 border border-slate-300',
    indicatorColor: 'bg-slate-500',
    gradientClasses: {
      from: 'from-slate-400',
      to: 'to-slate-500',
      border: 'border-slate-400'
    }
  },
  'Legacy Orders': {
    name: 'Legacy Orders',
    shortName: 'Legacy',
    badgeColor: 'bg-gray-200 text-gray-900 border border-gray-300',
    indicatorColor: 'bg-gray-500',
    gradientClasses: {
      from: 'from-gray-600',
      to: 'to-gray-700',
      border: 'border-gray-500'
    }
  }
}

export function getSystemShortName(source: string): string {
  return systemMapping[source]?.shortName || 'UN'
}

export function getSystemBadgeColor(source: string): string {
  return systemMapping[source]?.badgeColor || 'bg-gray-100 text-gray-800 border border-gray-200'
}

export function getSystemIndicatorColor(source: string): string {
  return systemMapping[source]?.indicatorColor || 'bg-gray-500'
}

export function getSystemGradientClasses(source: string): { from: string; to: string; border: string } {
  return systemMapping[source]?.gradientClasses || {
    from: 'from-gray-600',
    to: 'to-gray-700',
    border: 'border-gray-500'
  }
}

export function getAllSystemNames(): string[] {
  return Object.keys(systemMapping)
}

export function getSystemInfo(source: string): SystemInfo | null {
  return systemMapping[source] || null
}
