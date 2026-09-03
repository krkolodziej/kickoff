import { cva, type VariantProps } from 'class-variance-authority'
import type { ButtonHTMLAttributes } from 'react'

import { cn } from '@/lib/cn'

const button = cva(
  'inline-flex items-center justify-center gap-2 rounded-[var(--radius-control)] font-medium whitespace-nowrap transition-[background-color,border-color,box-shadow,opacity] duration-150 disabled:pointer-events-none disabled:opacity-55',
  {
    variants: {
      variant: {
        primary: 'bg-primary text-primary-foreground hover:bg-primary-hover shadow-sm',
        outline: 'border border-border-strong bg-surface text-foreground hover:bg-surface-muted',
        ghost: 'text-foreground-muted hover:bg-surface-muted hover:text-foreground',
        danger: 'bg-danger text-white hover:opacity-90',
      },
      size: {
        sm: 'h-8 px-3 text-[13px]',
        md: 'h-10 px-4 text-sm',
        lg: 'h-11 px-5 text-[15px]',
        icon: 'size-9',
      },
    },
    defaultVariants: { variant: 'primary', size: 'md' },
  },
)

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & VariantProps<typeof button>

export function Button({ className, variant, size, ...props }: ButtonProps) {
  return <button className={cn(button({ variant, size }), className)} {...props} />
}
