import ConfirmDialog from '~/components/ConfirmDialog.vue'

/**
 * Ask before destructive actions (spec §6):
 *   if (await confirm({ title: 'Archive client?', confirmLabel: 'Archive' })) { ... }
 */
export function useConfirm() {
  const overlay = useOverlay()
  const dialog = overlay.create(ConfirmDialog)

  return (props: {
    title: string
    description?: string
    confirmLabel?: string
    color?: 'error' | 'warning' | 'primary'
  }): Promise<boolean> => dialog.open(props).then((result) => result === true)
}
