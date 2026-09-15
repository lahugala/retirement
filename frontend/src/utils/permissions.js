// Role-based permission definitions
const ROLE_HIERARCHY = {
  admin: 100,
  treasurer: 80,
  board: 60,
  organizer: 40,
  member: 20,
}

export function hasPermission(userRole, minimumRole) {
  return (ROLE_HIERARCHY[userRole] || 0) >= (ROLE_HIERARCHY[minimumRole] || 0)
}

export function canAccess(menuKey, userRole) {
  const access = {
    dashboard: ['admin', 'treasurer', 'board', 'organizer', 'member'],
    events: ['admin', 'treasurer', 'organizer', 'board'],
    transactions: ['admin', 'treasurer', 'board', 'organizer'],
    members: ['admin', 'treasurer', 'board', 'organizer'],
    users: ['admin', 'treasurer', 'board'],
    categories: ['admin', 'treasurer'],
    accounts: ['admin', 'treasurer'],
    giftstock: ['admin', 'treasurer', 'board'],
    reports: ['admin', 'treasurer', 'board'],
    audit: ['admin', 'treasurer', 'board'],
    settings: ['admin'],
  }
  return (access[menuKey] || []).includes(userRole)
}

export const ROLE_LABELS = {
  admin: 'Admin',
  treasurer: 'Treasurer',
  organizer: 'Event Organizer',
  board: 'Board Member',
  member: 'Member',
}

export const ROLE_COLORS = {
  admin: 'red',
  treasurer: 'gold',
  organizer: 'blue',
  board: 'purple',
  member: 'green',
}
