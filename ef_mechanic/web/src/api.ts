const resourceName =
  typeof (window as unknown as { GetParentResourceName?: () => string }).GetParentResourceName ===
  'function'
    ? (window as unknown as { GetParentResourceName: () => string }).GetParentResourceName()
    : 'ef_mechanic'

export async function nuiCallback<T = unknown>(
  event: string,
  data: Record<string, unknown> = {},
): Promise<T> {
  const response = await fetch(`https://${resourceName}/${event}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json; charset=UTF-8' },
    body: JSON.stringify(data),
  })

  try {
    return (await response.json()) as T
  } catch {
    return undefined as T
  }
}

export function closeTablet(): Promise<unknown> {
  return nuiCallback('close')
}
