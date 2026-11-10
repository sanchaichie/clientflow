import type { ClientStatus, ProjectStatus, TaskPriority, TaskStatus } from '~/types/api'

/**
 * Labels and badge colours for every enum the API uses, in one place so the
 * tables, badges, filters and forms always agree. Values mirror backend/app/Enums.
 */
type BadgeColor = 'primary' | 'secondary' | 'success' | 'info' | 'warning' | 'error' | 'neutral'

interface Option<T extends string> {
  value: T
  label: string
  color: BadgeColor
}

export const clientStatuses: Option<ClientStatus>[] = [
  { value: 'lead', label: 'Lead', color: 'info' },
  { value: 'active', label: 'Active', color: 'success' },
  { value: 'inactive', label: 'Inactive', color: 'neutral' }
]

export const projectStatuses: Option<ProjectStatus>[] = [
  { value: 'planning', label: 'Planning', color: 'info' },
  { value: 'active', label: 'Active', color: 'primary' },
  { value: 'on_hold', label: 'On Hold', color: 'warning' },
  { value: 'completed', label: 'Completed', color: 'success' },
  { value: 'cancelled', label: 'Cancelled', color: 'neutral' }
]

export const taskStatuses: Option<TaskStatus>[] = [
  { value: 'todo', label: 'To Do', color: 'neutral' },
  { value: 'in_progress', label: 'In Progress', color: 'info' },
  { value: 'review', label: 'Review', color: 'warning' },
  { value: 'completed', label: 'Completed', color: 'success' }
]

export const taskPriorities: Option<TaskPriority>[] = [
  { value: 'low', label: 'Low', color: 'neutral' },
  { value: 'medium', label: 'Medium', color: 'info' },
  { value: 'high', label: 'High', color: 'warning' },
  { value: 'urgent', label: 'Urgent', color: 'error' }
]

export const statusOptions = {
  client: clientStatuses,
  project: projectStatuses,
  task: taskStatuses,
  priority: taskPriorities
} as const

export type OptionKind = keyof typeof statusOptions

export function findOption(kind: OptionKind, value: string | null | undefined) {
  return (statusOptions[kind] as Option<string>[]).find((o) => o.value === value)
}

/** "2026-12-01" → "Dec 1, 2026" (dates come from the API as plain strings). */
export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(value.length === 10 ? `${value}T00:00:00` : value)
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}
