import { X } from 'lucide-react'
import { useEffect, useRef, type ReactNode } from 'react'

import { cn } from '@/lib/cn'

interface DialogProps {
  open: boolean
  onClose: () => void
  title: string
  description?: string
  children: ReactNode
  className?: string
}

/**
 * Built on the platform's own `<dialog>` rather than on a headless library.
 *
 * `showModal()` brings the focus trap, the inert background, the top layer and Escape
 * handling with it — all the things a hand-rolled modal gets wrong. What the element does
 * not give us is a click-outside-to-close, so that is the one behaviour implemented here,
 * by checking whether the click landed on the backdrop rather than on the panel.
 */
export function Dialog({ open, onClose, title, description, children, className }: DialogProps) {
  const ref = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const dialog = ref.current

    if (!dialog) {
      return
    }

    if (open && !dialog.open) {
      dialog.showModal()
    } else if (!open && dialog.open) {
      dialog.close()
    }
  }, [open])

  return (
    <dialog
      ref={ref}
      // Escape closes the dialog natively; this keeps React's state in step with it.
      onCancel={(event) => {
        event.preventDefault()
        onClose()
      }}
      onClick={(event) => {
        if (event.target === ref.current) {
          onClose()
        }
      }}
      aria-labelledby="dialog-title"
      className={cn(
        // `m-auto` is not decoration. A modal <dialog> is centred by the user agent's own
        // `inset: 0; margin: auto`, and Tailwind's preflight resets every margin to 0 —
        // which silently leaves the dialog pinned to the top-left corner.
        'm-auto w-[min(30rem,calc(100vw-2rem))] rounded-[var(--radius-card)] border border-border bg-surface p-0 text-foreground shadow-[var(--shadow-lift)]',
        'backdrop:bg-black/40 backdrop:backdrop-blur-[2px]',
        'open:animate-none',
        className,
      )}
    >
      <div className="flex items-start gap-4 border-b border-border px-6 py-5">
        <div className="min-w-0 flex-1">
          <h2 id="dialog-title" className="text-lg">
            {title}
          </h2>
          {description ? (
            <p className="mt-1 text-[13px] text-foreground-muted">{description}</p>
          ) : null}
        </div>

        <button
          type="button"
          onClick={onClose}
          aria-label="Close"
          className="-mr-1 -mt-1 grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] text-foreground-subtle transition-colors hover:bg-surface-muted hover:text-foreground"
        >
          <X className="size-4" />
        </button>
      </div>

      <div className="px-6 py-5">{children}</div>
    </dialog>
  )
}
