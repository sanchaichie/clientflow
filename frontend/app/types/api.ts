// Shapes of the JSON returned by the Laravel API (see backend/app/Http/Resources).

export type RoleSlug = 'admin' | 'project_manager' | 'team_member'

export interface User {
  id: number
  name: string
  email: string
  is_active: boolean
  role?: { slug: RoleSlug; name: string }
  created_at: string | null
}

export type ClientStatus = 'lead' | 'active' | 'inactive'
export type ProjectStatus = 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled'
export type TaskStatus = 'todo' | 'in_progress' | 'review' | 'completed'
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent'

export interface Client {
  id: number
  company_name: string
  contact_person: string | null
  email: string | null
  phone: string | null
  address: string | null
  description: string | null
  status: ClientStatus
  account_manager_id: number | null
  account_manager?: User | null
  projects_count?: number
  projects?: Project[]
  archived_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface Project {
  id: number
  name: string
  description: string | null
  status: ProjectStatus
  progress: number
  start_date: string | null
  end_date: string | null
  client_id: number
  client?: { id: number; company_name: string }
  manager_id: number
  manager?: User
  members?: User[]
  members_count?: number
  tasks_count?: number
  archived_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface Task {
  id: number
  title: string
  description: string | null
  status: TaskStatus
  priority: TaskPriority
  due_date: string | null
  is_overdue: boolean
  position: number
  completed_at: string | null
  project_id: number
  project?: { id: number; name: string; manager_id: number }
  assignee_id: number | null
  assignee?: User | null
  creator?: User | null
  comments_count?: number
  created_at: string | null
  updated_at: string | null
}

export interface ActivityLog {
  id: number
  action: string
  properties: Record<string, unknown>
  project_id: number | null
  subject_type: string
  subject_id: number
  user?: User | null
  created_at: string | null
}

export type CommentType = 'comment' | 'work_update'

export interface TaskComment {
  id: number
  task_id: number
  type: CommentType
  body: string
  user_id: number
  author?: User
  created_at: string | null
}

export type NotificationKind =
  'task_assigned' | 'task_status_changed' | 'task_commented' | 'project_assigned' | 'task_due_soon'

export interface AppNotification {
  id: string
  kind: NotificationKind
  message: string
  url: string
  excerpt?: string
  task_id?: number
  project_id?: number
  actor?: string | null
  read_at: string | null
  created_at: string | null
}

export interface ReportData {
  range: { from: string | null; to: string | null }
  projects_by_status: Record<ProjectStatus, number>
  tasks_by_status: Record<TaskStatus, number>
  completion: { total: number; completed: number; pending: number; rate: number }
  overdue: { count: number; tasks: Task[] }
  members: ({ user_id: number; name: string } & ReportFigures)[]
  unassigned: ReportFigures
}

export interface ReportFigures {
  assigned: number
  completed: number
  pending: number
  overdue: number
  completion_rate: number
}

export interface DashboardData {
  stats: {
    total_clients: number | null
    active_projects: number
    completed_projects: number
    pending_tasks: number
    overdue_tasks: number
    due_today_tasks: number
  }
  scope: 'my_tasks' | 'all_visible'
  project_progress: Project[]
  upcoming_tasks: Task[]
  recent_activity: ActivityLog[]
}

/** Laravel paginated resource collection. */
export interface Paginated<T> {
  data: T[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

/** Single-resource wrapper: { data: {...} }. */
export interface Item<T> {
  data: T
}
