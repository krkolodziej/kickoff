import type { InputHTMLAttributes, ReactNode } from 'react'
import { useId } from 'react'

import { cn } from '@/lib/cn'

export type FieldProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string
  hint?: ReactNode
  error?: string
}

/**
 * Label, control, and then either the hint or the error — always in that order.
 *
 * Putting the message between the label and the input is the common mistake: it makes rows
 * in a two-column grid stop lining up the moment one field is invalid, and the whole form
 * jumps as the user tabs through it.
 */
export function Field({ label, hint, error, className, id, ...props }: FieldProps) {
  const generatedId = useId()
  const fieldId = id ?? generatedId
  const messageId = `${fieldId}-message`
  const hasMessage = Boolean(error ?? hint)

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={fieldId} className="text-[13px] font-medium text-foreground-muted">
        {label}
      </label>

      <input
        id={fieldId}
        aria-invalid={error ? true : undefined}
        aria-describedby={hasMessage ? messageId : undefined}
        className={cn(
          'h-10 w-full rounded-[var(--radius-control)] border bg-surface px-3 text-sm text-foreground',
          'placeholder:text-foreground-subtle',
          'transition-[border-color,box-shadow] duration-150',
          'focus:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25',
          error ? 'border-danger' : 'border-border-strong',
          className,
        )}
        {...props}
      />

      {hasMessage ? (
        <p
          id={messageId}
          className={cn('text-[12.5px]', error ? 'text-danger' : 'text-foreground-subtle')}
        >
          {error ?? hint}
        </p>
      ) : null}
    </div>
  )
}
