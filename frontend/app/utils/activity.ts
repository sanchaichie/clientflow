import type { ActivityLog, NotificationKind } from '~/types/api'

/**
 * Turns an activity_logs row into a short sentence (without the actor's name,
 * which the feed shows separately) and an icon.
 * Actions mirror the backend: App\Services\{Task,Project,TaskComment}Service.
 */
export function describeActivity(log: ActivityLog): { text: string; icon: string } {
  const p = log.properties as Record<string, unknown>
  const task = `"${String(p.title ?? 'a task')}"`
  const project = `"${String(p.name ?? 'a project')}"`
  const status = (value: unknown) => findOption('task', String(value))?.label ?? String(value)
  const projectStatus = (value: unknown) =>
    findOption('project', String(value))?.label ?? String(value)
  const list = (value: unknown) => (Array.isArray(value) ? value.join(', ') : String(value ?? ''))

  switch (log.action) {
    case 'task.created':
      return { text: `created task ${task}`, icon: 'i-lucide-plus-circle' }
    case 'task.updated':
      return { text: `edited ${task} (${list(p.fields)})`, icon: 'i-lucide-pencil' }
    case 'task.assigned':
      return {
        text: p.to ? `assigned ${task} to ${String(p.to)}` : `unassigned ${task}`,
        icon: 'i-lucide-user-round-check'
      }
    case 'task.status_changed':
      return {
        text: `moved ${task} from ${status(p.from)} to ${status(p.to)}`,
        icon: p.to === 'completed' ? 'i-lucide-circle-check' : 'i-lucide-arrow-right-left'
      }
    case 'task.commented':
      return {
        text: `${p.type === 'work_update' ? 'posted a work update on' : 'commented on'} ${task}`,
        icon: p.type === 'work_update' ? 'i-lucide-clipboard-check' : 'i-lucide-message-square'
      }
    case 'task.deleted':
      return { text: `deleted task ${task}`, icon: 'i-lucide-trash-2' }
    case 'project.created':
      return { text: `created project ${project}`, icon: 'i-lucide-folder-plus' }
    case 'project.updated':
      return { text: `edited ${project} (${list(p.fields)})`, icon: 'i-lucide-pencil' }
    case 'project.status_changed':
      return {
        text: `changed ${project} from ${projectStatus(p.from)} to ${projectStatus(p.to)}`,
        icon: 'i-lucide-flag'
      }
    case 'project.manager_changed':
      return { text: `made ${String(p.to)} manager of ${project}`, icon: 'i-lucide-user-cog' }
    case 'project.members_added':
      return { text: `added ${list(p.members)} to ${project}`, icon: 'i-lucide-user-plus' }
    case 'project.member_removed':
      return { text: `removed ${String(p.member)} from ${project}`, icon: 'i-lucide-user-minus' }
    case 'project.archived':
      return { text: `archived ${project}`, icon: 'i-lucide-archive' }
    case 'project.restored':
      return { text: `restored ${project}`, icon: 'i-lucide-archive-restore' }
    default:
      return { text: log.action, icon: 'i-lucide-activity' }
  }
}

export const notificationIcons: Record<NotificationKind, string> = {
  task_assigned: 'i-lucide-user-round-check',
  task_status_changed: 'i-lucide-arrow-right-left',
  task_commented: 'i-lucide-message-square',
  project_assigned: 'i-lucide-folder-plus',
  task_due_soon: 'i-lucide-alarm-clock'
}

/** "5 minutes ago", "yesterday", … */
export function timeAgo(value: string | null | undefined): string {
  if (!value) return ''
  const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000)
  const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['week', 604_800],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60]
  ]
  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit)
  }
  return 'just now'
}
