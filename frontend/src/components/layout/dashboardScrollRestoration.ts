import type { InjectionKey } from 'vue'

export interface DashboardScrollDestination {
  fullPath: string
  keepAlive: boolean
}

export type RestoreDashboardScroll = (destination: DashboardScrollDestination) => void

export const restoreDashboardScrollKey: InjectionKey<RestoreDashboardScroll> = Symbol(
  'restore-dashboard-scroll',
)
