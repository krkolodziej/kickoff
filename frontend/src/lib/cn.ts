import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

/** Merges class lists, letting a later conflicting Tailwind utility win. */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs))
}
