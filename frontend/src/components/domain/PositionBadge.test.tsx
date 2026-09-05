import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { PositionBadge } from './PositionBadge'
import { PLAYER_POSITIONS } from '@/api/types'

/**
 * The badge's whole design rests on one claim: the abbreviation carries the meaning and the
 * colour is decoration. That is what makes it readable in greyscale and to anyone who cannot
 * separate the four tones — and it is exactly the kind of rule a later refactor quietly drops
 * when someone decides a coloured dot looks tidier.
 *
 * So the assertions are about text, never about classes. A test that pinned the Tailwind
 * classes would break on every restyle while proving nothing about what a reader can tell.
 */
describe('PositionBadge', () => {
  it.each([
    ['GOALKEEPER', 'GK', 'Goalkeeper'],
    ['DEFENDER', 'DF', 'Defender'],
    ['MIDFIELDER', 'MF', 'Midfielder'],
    ['FORWARD', 'FW', 'Forward'],
  ] as const)('shows %s as %s, and says the whole word', (position, short, spoken) => {
    render(<PositionBadge position={position} />)

    expect(screen.getByText(short)).toBeInTheDocument()
    // The abbreviation is for the eye; the word is what a screen reader announces, because
    // "DF" read aloud in a list of names is not information.
    expect(screen.getByText(spoken)).toBeInTheDocument()
  })

  it('covers every position the API can send', () => {
    // Guards the Record above: adding a fifth position to the enum without a treatment for it
    // would otherwise fail at runtime on whichever page happened to show one first.
    for (const position of PLAYER_POSITIONS) {
      const { unmount } = render(<PositionBadge position={position} />)
      expect(screen.getByTitle(/\w/)).toBeInTheDocument()
      unmount()
    }
  })

  it('renders a dash rather than an empty badge when nobody has assigned one', () => {
    render(<PositionBadge position={null} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
