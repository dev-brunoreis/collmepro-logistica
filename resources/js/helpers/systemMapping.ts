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
  'Beauty': {
    name: 'Coll Me! Beauty',
    shortName: 'Beauty',
    badgeColor: 'bg-yellow-400 text-gray-900 border border-yellow-500',
    indicatorColor: 'bg-yellow-500',
    gradientClasses: {
      from: 'from-yellow-400',
      to: 'to-yellow-500',
      border: 'border-yellow-400'
    }
  },
  'Pro': {
    name: 'Coll Me! Pro',
    shortName: 'Pro',
    badgeColor: 'bg-blue-600 text-white border border-blue-700',
    indicatorColor: 'bg-blue-600',
    gradientClasses: {
      from: 'from-blue-600',
      to: 'to-blue-700',
      border: 'border-blue-500'
    }
  },
  'Atacadista': {
    name: 'Coll Me! Atacadista',
    shortName: 'Atacadista',
    badgeColor: 'bg-green-600 text-white border border-green-700',
    indicatorColor: 'bg-green-600',
    gradientClasses: {
      from: 'from-green-600',
      to: 'to-green-700',
      border: 'border-green-500'
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
    name: '-',
    shortName: '-',
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
  return systemMapping[source]?.shortName || '-'
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
