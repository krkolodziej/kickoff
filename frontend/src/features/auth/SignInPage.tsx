import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { z } from 'zod'

import { useDemoSignIn, useLogin } from '@/api/auth'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/ui/field'
import { AuthCard, FormError } from '@/features/auth/AuthLayout'
import { applyApiErrorToForm } from '@/lib/apiErrorToForm'

const schema = z.object({
  email: z.email('Enter a valid email address.'),
  password: z.string().min(1, 'Enter your password.'),
})

type Values = z.infer<typeof schema>

export function SignInPage() {
  const login = useLogin()
  const demo = useDemoSignIn()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '' },
  })

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)

    try {
      // No navigation here: RequireAnonymous redirects the moment the session exists, and it
      // is the only thing that can, so it is the only thing that decides where to.
      await login.mutateAsync(values)
    } catch (error) {
      setFormError(applyApiErrorToForm(error, setError, ['email', 'password']))
    }
  })

  const onDemo = async () => {
    setFormError(null)

    try {
      // Same again: the response says which season to open, the mutation records it, and the
      // guard does the rest. A visitor dropped on a list of one organization would have to
      // find the season themselves, and the season is why they pressed this.
      await demo.mutateAsync()
    } catch {
      // The endpoint answers 404 where the demonstration is switched off and 503 where it has
      // not been prepared yet. Neither is worth explaining to a visitor who only wanted a look
      // around, so the button simply says it is unavailable.
      setFormError('The demonstration is not available right now.')
    }
  }

  return (
    <AuthCard
      title="Sign in"
      subtitle="Run your leagues, fixtures and results."
      footer={
        <>
          No account yet?{' '}
          <Link to="/sign-up" className="font-medium text-primary hover:underline">
            Create one
          </Link>
        </>
      }
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <FormError message={formError} />

        <Field
          label="Email"
          type="email"
          autoComplete="email"
          autoFocus
          error={errors.email?.message}
          {...register('email')}
        />

        <Field
          label="Password"
          type="password"
          autoComplete="current-password"
          error={errors.password?.message}
          {...register('password')}
        />

        <Button type="submit" size="lg" disabled={isSubmitting} className="mt-1">
          {isSubmitting ? 'Signing in…' : 'Sign in'}
        </Button>
      </form>

      <div className="mt-6 flex items-center gap-3" aria-hidden="true">
        <span className="h-px flex-1 bg-border" />
        <span className="text-[11px] uppercase tracking-wide text-foreground-subtle">or</span>
        <span className="h-px flex-1 bg-border" />
      </div>

      <Button
        type="button"
        variant="outline"
        size="lg"
        className="mt-4 w-full"
        onClick={() => void onDemo()}
        disabled={demo.isPending}
      >
        {demo.isPending ? 'Opening the demo…' : 'Look around a finished season'}
      </Button>

      <p className="mt-2 text-center text-[12px] text-foreground-muted">
        Twelve clubs, thirteen rounds played. No account needed.
      </p>
    </AuthCard>
  )
}
