import { cn } from '@/lib/cn'

/** A pitch seen from above, drawn rather than imported, so it inherits the theme. */
export function BrandMark({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 32 32"
      aria-hidden="true"
      className={cn('size-7 shrink-0', className)}
      fill="none"
    >
      <rect x="1" y="1" width="30" height="30" rx="9" className="fill-primary" />
      <g stroke="var(--primary-foreground)" strokeWidth="1.4" strokeLinecap="round" opacity="0.9">
        <path d="M16 4.5v23" />
        <circle cx="16" cy="16" r="4.2" />
        <path d="M4.5 10.5h4v11h-4" />
        <path d="M27.5 10.5h-4v11h4" />
      </g>
    </svg>
  )
}
