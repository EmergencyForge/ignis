type Props = {
  title: string
  hint: string
}

export default function Overview({ title, hint }: Props) {
  return (
    <section className="panel">
      <h2>{title}</h2>
      <p>{hint}</p>
      <dl className="meta">
        <div>
          <dt>Plate</dt>
          <dd>—</dd>
        </div>
        <div>
          <dt>Model</dt>
          <dd>—</dd>
        </div>
        <div>
          <dt>km</dt>
          <dd>—</dd>
        </div>
      </dl>
    </section>
  )
}
