import { useTheme } from "next-themes"
import { Toaster as Sonner, type ToasterProps } from "sonner"

const Toaster = ({ theme: propsTheme, ...props }: ToasterProps) => {
  const { theme: themeFromHook = "system" } = useTheme()
  const theme = propsTheme ?? themeFromHook

  return (
    <Sonner
      {...props}
      theme={theme === undefined ? "system" : theme as ToasterProps["theme"]}
      className="toaster group"
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
        } as React.CSSProperties
      }
    />
  )
}

export { Toaster }
