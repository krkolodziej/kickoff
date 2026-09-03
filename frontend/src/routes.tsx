import { createBrowserRouter, Navigate } from 'react-router-dom'

import { AppShell } from '@/components/layout/AppShell'
import { RequireAnonymous, RequireAuth } from '@/components/RouteGuards'
import { AuthLayout } from '@/features/auth/AuthLayout'
import { SignInPage } from '@/features/auth/SignInPage'
import { SignUpPage } from '@/features/auth/SignUpPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { OrganizationPage } from '@/features/organizations/OrganizationPage'
import {
  ClubsSection,
  LeaguesSection,
  MembersSection,
  PlayersSection,
} from '@/features/organizations/sections'

export const router = createBrowserRouter([
  {
    element: <RequireAnonymous />,
    children: [
      {
        element: <AuthLayout />,
        children: [
          { path: '/sign-in', element: <SignInPage /> },
          { path: '/sign-up', element: <SignUpPage /> },
        ],
      },
    ],
  },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppShell />,
        children: [
          { path: '/dashboard', element: <DashboardPage /> },
          {
            path: '/organizations/:organizationId',
            element: <OrganizationPage />,
            children: [
              // The section is part of the address, so a tab can be linked to, bookmarked
              // and reached with the back button.
              { index: true, element: <Navigate to="leagues" replace /> },
              { path: 'leagues', element: <LeaguesSection /> },
              { path: 'clubs', element: <ClubsSection /> },
              { path: 'players', element: <PlayersSection /> },
              { path: 'members', element: <MembersSection /> },
            ],
          },
        ],
      },
    ],
  },
  { path: '/', element: <Navigate to="/dashboard" replace /> },
  { path: '*', element: <Navigate to="/dashboard" replace /> },
])
