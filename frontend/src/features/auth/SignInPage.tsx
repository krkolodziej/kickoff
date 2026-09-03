import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { z } from 'zod'

import { useLogin } from '@/api/auth'
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
  const navigate = useNavigate()
  const location = useLocation()
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
      await login.mutateAsync(values)

      const from = (location.state as { from?: string } | null)?.from
      navigate(from ?? '/dashboard', { replace: true })
    } catch (error) {
      setFormError(applyApiErrorToForm(error, setError, ['email', 'password']))
    }
  })

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
    </AuthCard>
  )
}
