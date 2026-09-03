import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'

import { useRegister } from '@/api/auth'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/ui/field'
import { AuthCard, FormError } from '@/features/auth/AuthLayout'
import { applyApiErrorToForm } from '@/lib/apiErrorToForm'

/**
 * The field names are snake_case because they are the wire format. Keeping the form's
 * shape identical to the request body is what lets the server's `fields` object be applied
 * to the form without a translation table in between.
 */
const schema = z
  .object({
    first_name: z.string().trim().max(100),
    last_name: z.string().trim().max(100),
    email: z.email('Enter a valid email address.'),
    password: z.string().min(8, 'Use at least 8 characters.'),
    password_confirm: z.string(),
  })
  .refine((values) => values.password === values.password_confirm, {
    path: ['password_confirm'],
    message: 'The two passwords do not match.',
  })

type Values = z.infer<typeof schema>

export function SignUpPage() {
  const registerAccount = useRegister()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      first_name: '',
      last_name: '',
      email: '',
      password: '',
      password_confirm: '',
    },
  })

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)

    try {
      await registerAccount.mutateAsync(values)
      navigate('/dashboard', { replace: true })
    } catch (error) {
      setFormError(
        applyApiErrorToForm(error, setError, [
          'first_name',
          'last_name',
          'email',
          'password',
          'password_confirm',
        ]),
      )
    }
  })

  return (
    <AuthCard
      title="Create your account"
      subtitle="Set up a workspace for your competition."
      footer={
        <>
          Already have an account?{' '}
          <Link to="/sign-in" className="font-medium text-primary hover:underline">
            Sign in
          </Link>
        </>
      }
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <FormError message={formError} />

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="First name"
            autoComplete="given-name"
            error={errors.first_name?.message}
            {...register('first_name')}
          />
          <Field
            label="Last name"
            autoComplete="family-name"
            error={errors.last_name?.message}
            {...register('last_name')}
          />
        </div>

        <Field
          label="Email"
          type="email"
          autoComplete="email"
          error={errors.email?.message}
          {...register('email')}
        />

        <Field
          label="Password"
          type="password"
          autoComplete="new-password"
          hint="At least 8 characters."
          error={errors.password?.message}
          {...register('password')}
        />

        <Field
          label="Confirm password"
          type="password"
          autoComplete="new-password"
          error={errors.password_confirm?.message}
          {...register('password_confirm')}
        />

        <Button type="submit" size="lg" disabled={isSubmitting} className="mt-1">
          {isSubmitting ? 'Creating account…' : 'Create account'}
        </Button>
      </form>
    </AuthCard>
  )
}
