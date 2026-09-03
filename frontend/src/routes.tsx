import { createBrowserRouter, Navigate } from 'react-router-dom'

import { AppShell } from '@/components/layout/AppShell'
import { RequireAnonymous, RequireAuth } from '@/components/RouteGuards'
import { AuthLayout } from '@/features/auth/AuthLayout'
import { SignInPage } from '@/features/auth/SignInPage'
import { SignUpPage } from '@/features/auth/SignUpPage'
import { LeaguePage } from '@/features/competitions/LeaguePage'
import {
  FixturesSection,
  SquadsSection,
} from '@/features/competitions/season-sections'
import { SeasonPage } from '@/features/competitions/SeasonPage'
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
          // Outside the organization's tab shell: a league has its own address and its
          // own seasons, and a season its own clubs and squads.
          { path: '/organizations/:organizationId/leagues/:leagueId', element: <LeaguePage /> },
          {
            path: '/organizations/:organizationId/leagues/:leagueId/seasons/:seasonId',
            element: <SeasonPage />,
            children: [
              { index: true, element: <Navigate to="squads" replace /> },
              { path: 'squads', element: <SquadsSection /> },
              { path: 'fixtures', element: <FixturesSection /> },
            ],
          },
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
