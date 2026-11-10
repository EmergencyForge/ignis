type Props = {
  title: string
  hint: string
}

export default function Placeholder({ title, hint }: Props) {
  return (
    <section className="panel">
      <h2>{title}</h2>
      <p>{hint}</p>
    </section>
  )
}
