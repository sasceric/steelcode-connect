export const localeFlag = (locale: string): string => {
  const region = locale.split('-').at(-1)?.toUpperCase() || ''

  if (!/^[A-Z]{2}$/.test(region)) {
    return '🌐'
  }

  return String.fromCodePoint(
    ...[...region].map((letter) => 0x1f1e6 + letter.charCodeAt(0) - 65),
  )
}
