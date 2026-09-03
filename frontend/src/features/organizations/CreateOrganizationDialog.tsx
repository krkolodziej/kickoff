import { zodResolver } from '@hookform/resolvers/zod'
import { useCallback, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'

import { useCreateOrganization } from '@/api/organizations'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { Field } from '@/components/ui/field'
import { FormError } from '@/features/auth/AuthLayout'
import { applyApiErrorToForm } from '@/lib/apiErrorToForm'

const schema = z.object({
  name: z.string().trim().min(2, 'Use at least 2 characters.'),
})

type Values = z.infer<typeof schema>

export function CreateOrganizationDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const createOrganization = useCreateOrganization()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { name: '' } })

  // Clearing on the way out rather than in an effect on the way in. A dialog that reopens
  // still holding the last attempt's error reads as though the new attempt has already
  // failed — but synchronising that with an effect would just cause an extra render for
  // something the close event already knows about.
  const close = useCallback(() => {
    reset({ name: '' })
    setFormError(null)
    onClose()
  }, [onClose, reset])

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)

    try {
      const organization = await createOrganization.mutateAsync(values)
      close()
      void navigate(`/organizations/${organization.id}`)
    } catch (error) {
      setFormError(applyApiErrorToForm(error, setError, ['name']))
    }
  })

  return (
    <Dialog
      open={open}
      onClose={close}
      title="New organization"
      description="A workspace for one association or club office. You will be its owner."
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <FormError message={formError} />

        <Field
          label="Name"
          autoFocus
          placeholder="Podkarpacka Liga Amatorska"
          hint="The address is derived from this, and made unique if it is already taken."
          error={errors.name?.message}
          {...register('name')}
        />

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={close}>
            Cancel
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Creating…' : 'Create organization'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
