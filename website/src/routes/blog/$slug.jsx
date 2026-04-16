import { createFileRoute, Link, notFound } from '@tanstack/react-router'
import { useState } from 'react'
import { getPostBySlug, getAllSlugs } from '@/data/posts'
import { Copy, Download, Check } from 'lucide-react'

export const Route = createFileRoute('/blog/$slug')({
  component: BlogPost,
  beforeLoad: ({ params }) => {
    const post = getPostBySlug(params.slug)
    if (!post) {
      throw notFound()
    }
  },
  loader: ({ params }) => {
    const post = getPostBySlug(params.slug)
    return { post }
  },
  head: ({ loaderData }) => ({
    meta: loaderData ? [
      { title: `${loaderData.post.title} — laraperf Blog` },
      { name: 'description', content: loaderData.post.description },
    ] : [],
  }),
  ssg: {
    getPaths: () => {
      const slugs = getAllSlugs()
      return slugs.map(slug => ({ slug }))
    },
  },
})

// Icon components
const Icons = {
  target: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <circle cx="12" cy="12" r="10"/>
      <circle cx="12" cy="12" r="6"/>
      <circle cx="12" cy="12" r="2"/>
    </svg>
  ),
  hash: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <line x1="4" y1="9" x2="20" y2="9"/>
      <line x1="4" y1="15" x2="20" y2="15"/>
      <line x1="10" y1="3" x2="8" y2="21"/>
      <line x1="16" y1="3" x2="14" y2="21"/>
    </svg>
  ),
  zap: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
    </svg>
  ),
  table: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <rect x="3" y="3" width="18" height="18" rx="0"/>
      <line x1="3" y1="9" x2="21" y2="9"/>
      <line x1="9" y1="3" x2="9" y2="21"/>
    </svg>
  ),
  gitBranch: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <line x1="6" y1="3" x2="6" y2="15"/>
      <circle cx="18" cy="6" r="3"/>
      <circle cx="6" cy="18" r="3"/>
      <path d="M18 9a9 9 0 0 1-9 9"/>
    </svg>
  ),
  timer: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <circle cx="12" cy="12" r="10"/>
      <polyline points="12 6 12 12 16 14"/>
    </svg>
  ),
  shield: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
    </svg>
  ),
  robot: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <rect x="3" y="11" width="18" height="10" rx="0"/>
      <circle cx="12" cy="5" r="2"/>
      <path d="M12 7v4"/>
      <line x1="8" y1="16" x2="8" y2="16"/>
      <line x1="16" y1="16" x2="16" y2="16"/>
    </svg>
  ),
  memory: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
      <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
    </svg>
  ),
  clock: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <circle cx="12" cy="12" r="10"/>
      <polyline points="12 6 12 12 16 14"/>
    </svg>
  ),
  detach: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
    </svg>
  )
}

function PostHeader({ post }) {
  return (
    <div className="max-w-6xl mx-auto px-4 md:px-8 pb-4">
      <Link
        to="/blog"
        className="inline-flex items-center gap-2 text-sm text-stone-400 hover:text-emerald-400 transition mb-6"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <line x1="19" y1="12" x2="5" y2="12" />
          <polyline points="12 19 5 12 12 5" />
        </svg>
        All articles
      </Link>
      <div className="flex flex-wrap items-center gap-4 text-xs text-stone-400 mb-8">
        <span>{post.author}</span>
        <span>·</span>
        <span>{new Date(post.date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</span>
        <span>·</span>
        <span>{post.readingTime}</span>
      </div>
    </div>
  )
}

// Block renderers
function HeroBlock({ block, eyebrow, post }) {
  if (block.layout === "split") {
    return (
      <section className="border-b border-stone-200 bg-stone-950/80 pt-8">
        {post && <PostHeader post={post} />}
        <div className="grid grid-cols-1 lg:grid-cols-2">
          <div className="p-8 lg:p-12 flex flex-col justify-center">
            <div className="text-emerald-400 text-xs font-mono tracking-widest mb-4">{block.left.eyebrow || eyebrow}</div>
            <h1 className="text-3xl lg:text-4xl font-bold text-stone-50 font-outfit leading-tight mb-6">
              {block.left.title}
            </h1>
            <p className="text-stone-300 text-lg leading-relaxed mb-8">
              {block.left.description}
            </p>
            <div className="flex items-baseline gap-2">
              <span className="text-4xl font-bold text-emerald-400">{block.left.stat.value}</span>
              <span className="text-stone-400 text-sm">{block.left.stat.label}</span>
            </div>
          </div>
          <div className="p-8 lg:p-12 flex items-center justify-center">
            <div className="w-full bg-stone-950 border border-stone-700/50 shadow-xl">
              <div className="flex items-center gap-1.5 px-4 py-2.5 border-b border-stone-800">
                <span className="w-2.5 h-2.5 rounded-full bg-red-400/80"/>
                <span className="w-2.5 h-2.5 rounded-full bg-amber-400/80"/>
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-400/80"/>
                <span className="text-xs text-stone-500 font-mono ml-2">php</span>
              </div>
              <pre className="p-4 text-sm font-mono text-stone-300 leading-relaxed overflow-x-auto">
                <code>{block.right.code}</code>
              </pre>
            </div>
          </div>
        </div>
      </section>
    )
  }
  
  if (block.layout === "center") {
    return (
      <section className="bg-stone-950/80 border-b border-stone-200 pt-8 pb-16 lg:pb-24 px-4">
        {post && <PostHeader post={post} />}
        <div className="max-w-4xl mx-auto text-center">
          <div className="text-emerald-400 text-xs font-mono tracking-widest mb-4">{block.eyebrow || eyebrow}</div>
          <h1 className="text-4xl lg:text-5xl font-bold text-stone-50 font-outfit leading-tight mb-6">
            {block.title}
          </h1>
          <p className="text-stone-300 text-lg lg:text-xl leading-relaxed max-w-3xl mx-auto mb-8">
            {block.description}
          </p>
          {block.stat && (
            <div className="inline-flex items-baseline gap-2 border border-emerald-900 bg-emerald-950/30 px-6 py-3">
              <span className="text-3xl font-bold text-emerald-400">{block.stat.value}</span>
              <span className="text-stone-400 text-sm">{block.stat.label}</span>
            </div>
          )}
        </div>
      </section>
    )
  }

  if (block.layout === "stats") {
    return (
      <section className="bg-stone-950/80 border-b border-stone-200 pt-8 pb-16 lg:pb-24 px-4">
        {post && <PostHeader post={post} />}
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-12">
            <div className="text-emerald-400 text-xs font-mono tracking-widest mb-4">{block.eyebrow || eyebrow}</div>
            <h1 className="text-4xl lg:text-5xl font-bold text-stone-50 font-outfit leading-tight mb-6">
              {block.title}
            </h1>
            <p className="text-stone-300 text-lg max-w-3xl mx-auto">
              {block.description}
            </p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-stone-800">
            {block.stats.map((stat, i) => (
              <div key={i} className="text-center py-8 px-4">
                <div className="text-3xl lg:text-4xl font-bold text-emerald-400 mb-2">{stat.value}</div>
                <div className="text-stone-400 text-sm">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>
    )
  }

  if (block.layout === "split-code") {
    return (
      <section className="bg-stone-950/80 border-b border-stone-200 pt-8">
        {post && <PostHeader post={post} />}
        <div className="max-w-4xl mx-auto px-4 pb-16 lg:pb-24">
          <div className="text-emerald-400 text-xs font-mono tracking-widest mb-4">{block.eyebrow || eyebrow}</div>
          <h1 className="text-3xl lg:text-4xl font-bold text-stone-50 font-outfit leading-tight mb-6">
            {block.title}
          </h1>
          <p className="text-stone-300 text-lg leading-relaxed">
            {block.description}
          </p>
        </div>
      </section>
    )
  }
}

function VisualizationBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-6xl mx-auto">
        <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-stone-200">
          {/* Before */}
          <div className="bg-red-950/20 p-8 lg:p-12">
            <div className="flex items-center gap-2 mb-6">
              <span className="w-2 h-2 bg-red-500"/>
              <span className="text-red-700 font-mono text-sm">{block.before.label}</span>
            </div>
            <div className="flex items-baseline gap-4 mb-8">
              <span className="text-4xl font-bold text-red-600">{block.before.queries}</span>
              <span className="text-red-600">queries</span>
              <span className="text-stone-400 ml-4">{block.before.time}</span>
            </div>
            <div className="space-y-2">
              {block.before.bars.map((bar, i) => (
                <div key={i} className="flex items-center gap-3">
                  <div 
                    className={`h-8 ${bar.type === 'main' ? 'bg-red-600' : 'bg-red-400/50'} ${bar.faded ? 'opacity-30' : ''}`}
                    style={{ width: `${bar.width}%` }}
                  />
                  <span className={`text-xs font-mono ${bar.faded ? 'text-stone-400' : 'text-stone-500'}`}>
                    {bar.label}
                  </span>
                </div>
              ))}
            </div>
          </div>
          {/* After */}
          <div className="bg-emerald-950/20 p-8 lg:p-12">
            <div className="flex items-center gap-2 mb-6">
              <span className="w-2 h-2 bg-emerald-500"/>
              <span className="text-emerald-700 font-mono text-sm">{block.after.label}</span>
            </div>
            <div className="flex items-baseline gap-4 mb-8">
              <span className="text-4xl font-bold text-emerald-600">{block.after.queries}</span>
              <span className="text-emerald-600">queries</span>
              <span className="text-stone-400 ml-4">{block.after.time}</span>
            </div>
            <div className="space-y-2">
              {block.after.bars.map((bar, i) => (
                <div key={i} className="flex items-center gap-3">
                  <div 
                    className={`h-8 ${bar.type === 'main' ? 'bg-emerald-600' : 'bg-emerald-400/70'}`}
                    style={{ width: `${bar.width}%` }}
                  />
                  <span className="text-xs font-mono text-stone-500">{bar.label}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

function CommandBlock({ block }) {
  return (
    <section className="bg-stone-950 py-16 lg:py-24 px-4 border-b border-stone-800">
      <div className="max-w-4xl mx-auto">
        <h2 className="text-2xl font-bold text-stone-50 font-outfit mb-4">{block.title}</h2>
        <p className="text-stone-400 mb-8">{block.description}</p>
        <div className="bg-stone-900 border border-stone-800">
          <div className="flex items-center gap-2 px-4 py-2 border-b border-stone-800">
            <span className="w-2 h-2 rounded-full bg-red-400"/>
            <span className="w-2 h-2 rounded-full bg-amber-400"/>
            <span className="w-2 h-2 rounded-full bg-emerald-400"/>
            <span className="text-xs text-stone-500 font-mono ml-auto">bash</span>
          </div>
          <div className="p-4">
            <code className="text-sm font-mono text-emerald-400">$ {block.command}</code>
          </div>
        </div>
        {block.output && (
          <pre className="mt-4 p-4 bg-stone-900/50 border border-stone-800 text-xs font-mono text-stone-300 overflow-x-auto">
            <code>{block.output}</code>
          </pre>
        )}
      </div>
    </section>
  )
}

function FixBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-6xl mx-auto">
        <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-stone-200">
          <div className="p-8 lg:p-12 bg-red-950/10">
            <div className="text-red-700 font-mono text-sm mb-6 flex items-center gap-2">
              <span className="w-2 h-2 bg-red-500"/>
              {block.before.highlight}
            </div>
            <pre className="text-sm font-mono text-stone-600 leading-relaxed">
              <code>{block.before.code}</code>
            </pre>
          </div>
          <div className="p-8 lg:p-12 bg-emerald-950/10">
            <div className="text-emerald-700 font-mono text-sm mb-6 flex items-center gap-2">
              <span className="w-2 h-2 bg-emerald-500"/>
              {block.after.highlight}
            </div>
            <pre className="text-sm font-mono text-stone-700 leading-relaxed">
              <code>{block.after.code}</code>
            </pre>
          </div>
        </div>
      </div>
    </section>
  )
}

function FeaturesBlock({ block }) {
  const getIcon = (name) => {
    const Icon = Icons[name] || Icons.zap
    return <Icon />
  }

  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-6xl mx-auto">
        <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-stone-200 divide-y divide-x">
          {block.items.map((item, i) => (
            <div key={i} className={`p-8 ${i % 2 === 0 ? 'bg-stone-50' : 'bg-stone-100/50'}`}>
              <div className="w-10 h-10 text-emerald-600 mb-4">
                {getIcon(item.icon)}
              </div>
              <h3 className="text-lg font-bold text-stone-900 font-outfit mb-2">{item.title}</h3>
              <p className="text-stone-500 text-sm leading-relaxed">{item.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

function CTABlock({ block }) {
  if (block.layout === "dark") {
    return (
      <section className="bg-stone-950 py-16 lg:py-24 px-4 border-b border-stone-800">
        <div className="max-w-4xl mx-auto">
          <h2 className="text-2xl lg:text-3xl font-bold text-stone-50 font-outfit mb-4">
            {block.title}
          </h2>
          <p className="text-stone-400 text-lg mb-12">{block.description}</p>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-stone-800 border border-stone-800">
            {block.steps.map((step, i) => (
              <div key={i} className="p-6 flex items-start gap-4">
                <span className="w-8 h-8 bg-emerald-950 border border-emerald-900 text-emerald-400 font-mono text-sm flex items-center justify-center shrink-0">
                  {i + 1}
                </span>
                <span className="text-stone-300 text-sm">{step}</span>
              </div>
            ))}
          </div>
        </div>
      </section>
    )
  }

  if (block.layout === "split") {
    return (
      <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
        <div className="max-w-6xl mx-auto">
          <div className="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-stone-200">
            <div className="p-8 lg:p-12">
              <h2 className="text-2xl font-bold text-stone-900 font-outfit mb-4">{block.left.title}</h2>
              <p className="text-stone-500">{block.left.description}</p>
            </div>
            <div className="p-8 lg:p-12 bg-stone-50">
              <ul className="space-y-3">
                {block.right.features.map((feature, i) => (
                  <li key={i} className="flex items-center gap-3 text-stone-700 text-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" className="text-emerald-600">
                      <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    {feature}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      </section>
    )
  }
}

function DiagnosticBlock({ block }) {
  const getStatus = (status) => {
    const colors = {
      good: "bg-emerald-500",
      warning: "bg-amber-500", 
      bad: "bg-red-500"
    }
    return colors[status] || "bg-stone-500"
  }

  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-6xl mx-auto">
        <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-stone-200">
          {block.items.map((item, i) => (
            <div key={i} className={`p-8 ${i % 2 === 0 ? 'bg-stone-50' : 'bg-stone-100/30'}`}>
              <div className="flex items-center gap-3 mb-4">
                <span className={`w-3 h-3 ${getStatus(item.status)}`}/>
                <span className="font-mono text-sm text-stone-900">{item.label}</span>
              </div>
              <p className="text-stone-500 text-sm mb-4">{item.description}</p>
              <div className="text-xs font-mono text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-2 inline-block">
                Fix: {item.fix}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

function BeforeAfterBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200 bg-stone-50">
      <div className="max-w-4xl mx-auto">
        <h2 className="text-2xl font-bold text-stone-900 font-outfit mb-2 text-center">{block.title}</h2>
        <p className="text-stone-500 text-center mb-12">{block.subtitle}</p>
        <div className="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-stone-200 border border-stone-200">
          <div className="p-8 bg-white">
            <div className="text-stone-400 text-xs font-mono mb-6">BEFORE</div>
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-stone-500 text-sm">Plan</span>
                <span className="font-mono text-red-600">{block.before.plan}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-stone-500 text-sm">Time</span>
                <span className="font-mono text-red-600">{block.before.time}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-stone-500 text-sm">Rows</span>
                <span className="font-mono text-stone-700">{block.before.rows}</span>
              </div>
              <p className="text-stone-400 text-sm pt-4 border-t border-stone-100">{block.before.note}</p>
            </div>
          </div>
          <div className="p-8 bg-emerald-950/10">
            <div className="text-emerald-700 text-xs font-mono mb-6">AFTER</div>
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-stone-500 text-sm">Plan</span>
                <span className="font-mono text-emerald-600">{block.after.plan}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-stone-500 text-sm">Time</span>
                <span className="font-mono text-emerald-600">{block.after.time}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-stone-500 text-sm">Rows</span>
                <span className="font-mono text-stone-700">{block.after.rows}</span>
              </div>
              <p className="text-stone-500 text-sm pt-4 border-t border-emerald-200/50">{block.after.note}</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

function WorkflowBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-4xl mx-auto">
        <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="space-y-0">
          {block.steps.map((step, i) => (
            <div key={i} className="flex gap-6">
              <div className="flex flex-col items-center">
                <span className="w-10 h-10 aspect-square bg-stone-950 text-stone-50 font-mono text-sm flex items-center justify-center shrink-0">
                  {step.number}
                </span>
                {i < block.steps.length - 1 && (
                  <div className="w-px flex-1 bg-stone-300 my-2 min-h-[2rem]"/>
                )}
              </div>
              <div className={`pb-12 ${i === block.steps.length - 1 ? '' : ''}`}>
                <h3 className="text-lg font-bold text-stone-900 font-outfit mb-1">{step.title}</h3>
                <p className="text-stone-500 text-sm">{step.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

function TipBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-4xl mx-auto">
        <div className="bg-emerald-950/10 border border-emerald-900/30 p-8 lg:p-12">
          <h3 className="text-xl font-bold text-emerald-900 font-outfit mb-4">{block.title}</h3>
          <p className="text-stone-600 mb-6">{block.content}</p>
          <code className="text-sm font-mono text-emerald-700 bg-white border border-emerald-200 px-3 py-2 inline-block">
            {block.command}
          </code>
        </div>
      </div>
    </section>
  )
}

function ComparisonBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-6xl mx-auto">
        <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-stone-200">
          <div className="p-8 lg:p-12 bg-stone-100/50">
            <div className="text-stone-400 text-xs font-mono tracking-widest mb-6">{block.left.label}</div>
            <ul className="space-y-4">
              {block.left.items.map((item, i) => (
                <li key={i} className="flex items-start gap-3 text-stone-500 text-sm">
                  <span className="text-stone-300 mt-0.5">—</span>
                  {item}
                </li>
              ))}
            </ul>
          </div>
          <div className="p-8 lg:p-12 bg-emerald-950/10">
            <div className="text-emerald-700 text-xs font-mono tracking-widest mb-6">{block.right.label}</div>
            <ul className="space-y-4">
              {block.right.items.map((item, i) => (
                <li key={i} className="flex items-start gap-3 text-stone-700 text-sm">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" className="text-emerald-600 mt-0.5 shrink-0">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </section>
  )
}

function SessionBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200 bg-stone-950">
      <div className="max-w-4xl mx-auto">
        <h2 className="text-2xl font-bold text-stone-50 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="space-y-6">
          {block.events.map((event, i) => (
            <div key={i} className="flex gap-6">
              <div className="w-16 text-right">
                <span className="text-stone-500 font-mono text-sm">{event.time}</span>
              </div>
              <div className="flex-1 pb-6 border-b border-stone-800">
                {event.command && (
                  <code className="text-sm font-mono text-emerald-400 block mb-2">$ {event.command}</code>
                )}
                {event.output && (
                  <pre className="text-xs font-mono text-stone-500">{event.output}</pre>
                )}
                {event.note && (
                  <p className="text-stone-400 text-sm italic">{event.note}</p>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

function IntegrationBlock({ block }) {
  const getIcon = (name) => {
    const Icon = Icons[name] || Icons.zap
    return <Icon />
  }

  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-6xl mx-auto">
        <div className="text-center mb-12">
          <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-4">
            {block.title}
          </h2>
          <p className="text-stone-500">{block.description}</p>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-stone-200">
          {block.items.map((item, i) => (
            <div key={i} className={`p-8 ${i % 2 === 0 ? 'bg-stone-50' : 'bg-stone-100/30'}`}>
              <div className="w-10 h-10 text-emerald-600 mb-4">
                {getIcon(item.icon)}
              </div>
              <h3 className="text-lg font-bold text-stone-900 font-outfit mb-2">{item.title}</h3>
              <p className="text-stone-500 text-sm">{item.content || item.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

function YamlBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200 bg-stone-900">
      <div className="max-w-4xl mx-auto">
        <h2 className="text-2xl font-bold text-stone-50 font-outfit mb-8">{block.title}</h2>
        <div className="bg-stone-950 border border-stone-800">
          <div className="flex items-center gap-2 px-4 py-2 border-b border-stone-800">
            <span className="w-2 h-2 rounded-full bg-red-400"/>
            <span className="w-2 h-2 rounded-full bg-amber-400"/>
            <span className="w-2 h-2 rounded-full bg-emerald-400"/>
            <span className="text-xs text-stone-500 font-mono ml-auto">.github/workflows/perf.yml</span>
          </div>
          <pre className="p-4 text-xs font-mono text-stone-300 overflow-x-auto">
            <code>{block.code}</code>
          </pre>
        </div>
      </div>
    </section>
  )
}

function SafetyBlock({ block }) {
  const getIcon = (name) => {
    const Icon = Icons[name] || Icons.zap
    return <Icon />
  }

  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-6xl mx-auto">
        <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-stone-200">
          {block.items.map((item, i) => (
            <div key={i} className={`p-8 lg:p-12 ${i % 2 === 0 ? 'bg-stone-50' : 'bg-stone-100/30'}`}>
              <div className="w-10 h-10 text-emerald-600 mb-4">
                {getIcon(item.icon)}
              </div>
              <h3 className="text-lg font-bold text-stone-900 font-outfit mb-2">{item.title}</h3>
              <p className="text-stone-500 text-sm leading-relaxed">{item.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

function BestPracticesBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-4xl mx-auto">
        <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-stone-200">
          <div className="p-8 lg:p-12">
            <div className="flex items-center gap-2 mb-6">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" className="text-emerald-600">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              <span className="text-emerald-700 font-mono text-sm">DO</span>
            </div>
            <ul className="space-y-3">
              {block.do.map((item, i) => (
                <li key={i} className="flex items-start gap-3 text-stone-700 text-sm">
                  <span className="text-emerald-600 mt-0.5">✓</span>
                  {item}
                </li>
              ))}
            </ul>
          </div>
          <div className="p-8 lg:p-12 bg-red-950/10">
            <div className="flex items-center gap-2 mb-6">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" className="text-red-600">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
              <span className="text-red-700 font-mono text-sm">DON'T</span>
            </div>
            <ul className="space-y-3">
              {block.dont.map((item, i) => (
                <li key={i} className="flex items-start gap-3 text-stone-600 text-sm">
                  <span className="text-red-500 mt-0.5">×</span>
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </section>
  )
}

function InsightsBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200 bg-stone-50">
      <div className="max-w-6xl mx-auto">
        <h2 className="text-2xl lg:text-3xl font-bold text-stone-900 font-outfit mb-12 text-center">
          {block.title}
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-stone-200">
          {block.items.map((item, i) => (
            <div key={i} className="p-8">
              <h3 className="text-lg font-bold text-stone-900 font-outfit mb-2">{item.title}</h3>
              <p className="text-stone-500 text-sm">{item.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

function MultiTenantBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-4xl mx-auto text-center">
        <h2 className="text-2xl font-bold text-stone-900 font-outfit mb-4">{block.title}</h2>
        <p className="text-stone-500 mb-8">{block.description}</p>
        <code className="text-sm font-mono text-emerald-700 bg-emerald-50 border border-emerald-200 px-4 py-3 inline-block mb-4">
          {block.command}
        </code>
        <p className="text-stone-400 text-sm">{block.note}</p>
      </div>
    </section>
  )
}

function CleanupBlock({ block }) {
  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200 bg-stone-950">
      <div className="max-w-4xl mx-auto">
        <h2 className="text-2xl font-bold text-stone-50 font-outfit mb-4">{block.title}</h2>
        <p className="text-stone-400 mb-8">{block.description}</p>
        <div className="bg-stone-900 border border-stone-800">
          <div className="flex items-center gap-2 px-4 py-2 border-b border-stone-800">
            <span className="w-2 h-2 rounded-full bg-red-400"/>
            <span className="w-2 h-2 rounded-full bg-amber-400"/>
            <span className="w-2 h-2 rounded-full bg-emerald-400"/>
            <span className="text-xs text-stone-500 font-mono ml-auto">bash</span>
          </div>
          <div className="p-4">
            <code className="text-sm font-mono text-emerald-400">$ {block.command}</code>
          </div>
        </div>
        {block.output && (
          <div className="mt-4 text-emerald-400 text-sm font-mono">{block.output}</div>
        )}
      </div>
    </section>
  )
}

function TextBlock({ block }) {
  // Simple markdown-like rendering for bold and code
  const renderContent = (content) => {
    if (!content) return null
    
    // Process bold text **text**
    let processed = content.replace(/\*\*(.*?)\*\*/g, '<strong class="text-stone-900 font-semibold">$1</strong>')
    // Process inline code `code`
    processed = processed.replace(/`([^`]+)`/g, '<code class="text-sm font-mono text-emerald-700 bg-emerald-50/50 px-1.5 py-0.5">$1</code>')
    
    // Split by double newlines to create paragraphs
    const paragraphs = processed.split('\n\n')
    
    return paragraphs.map((para, i) => (
      <p 
        key={i} 
        className="text-stone-600 leading-relaxed mb-4 last:mb-0"
        dangerouslySetInnerHTML={{ __html: para }}
      />
    ))
  }

  return (
    <section className="py-12 lg:py-16 px-4 border-b border-stone-200 bg-stone-50">
      <div className="max-w-4xl mx-auto">
        {block.title && (
          <h2 className="text-xl lg:text-2xl font-bold text-stone-900 font-outfit mb-6">
            {block.title}
          </h2>
        )}
        <div className="text-lg">
          {renderContent(block.content)}
        </div>
      </div>
    </section>
  )
}

function InstallCTABlock() {
  const [copied, setCopied] = useState(false)
  const [copiedSkill, setCopiedSkill] = useState(false)
  const [copiedPrompt, setCopiedPrompt] = useState(false)

  const promptText = 'Fetch and read the laraperf skill from https://laraperf.dev/skill.md, then install the package in this Laravel project using composer require mateffy/laraperf --dev. Run a quick performance capture to verify it\'s working.'

  return (
    <section className="py-16 lg:py-24 px-4 border-b border-stone-200">
      <div className="max-w-4xl mx-auto">
        <div className="text-center mb-12">
          <Download size={24} className="mx-auto mb-4 text-emerald-600" />
          <h2 className="text-3xl font-bold text-stone-900 font-outfit">Get started</h2>
          <p className="mt-3 text-stone-500">Two ways to install — manual or let your agent handle it.</p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-px bg-stone-200">
          <div className="bg-white p-8 lg:p-10">
            <h3 className="text-lg font-bold text-stone-900 mb-6 font-outfit">Manual install</h3>
            <div className="space-y-6">
              <div>
                <div className="flex items-center gap-2 mb-3">
                  <span className="w-5 h-5 rounded-full bg-emerald-600 text-white text-xs font-bold flex items-center justify-center shrink-0">1</span>
                  <span className="font-bold text-stone-900 text-sm">Install via Composer</span>
                </div>
                <div className="bg-stone-950 rounded-lg p-3 font-mono text-sm text-emerald-300 flex items-center justify-between gap-2">
                  <code className="text-xs">composer require mateffy/laraperf --dev</code>
                  <button
                    onClick={() => { navigator.clipboard?.writeText('composer require mateffy/laraperf --dev'); setCopied(true); setTimeout(() => setCopied(false), 2000) }}
                    className="shrink-0 text-stone-500 hover:text-stone-300 transition"
                    title="Copy"
                  >
                    {copied ? <Check size={12} /> : <Copy size={12} />}
                  </button>
                </div>
              </div>
              <div>
                <div className="flex items-center gap-2 mb-3">
                  <span className="w-5 h-5 rounded-full bg-stone-300 text-stone-600 text-xs font-bold flex items-center justify-center shrink-0">2</span>
                  <span className="font-bold text-stone-700 text-sm">Optional: env config</span>
                </div>
                <div className="bg-stone-950 rounded-lg p-3 font-mono text-xs text-stone-300 leading-5">
                  <div className="text-stone-500"># Connection for perf:explain</div>
                  <div><span className="text-blue-300">PERF_CONNECTION</span>=<span className="text-emerald-300">pgsql</span></div>
                </div>
              </div>
            </div>
          </div>

          <div className="bg-emerald-950 p-8 lg:p-10">
            <h3 className="text-lg font-bold text-white mb-4 font-outfit">Let your agent do it</h3>
            <p className="text-emerald-200/80 text-sm mb-4 leading-relaxed">
              Install the skill permanently with the CLI, or paste a prompt for a one-shot setup.
            </p>
            <div className="bg-stone-950/60 rounded-lg p-3 font-mono text-sm text-emerald-300 flex items-center justify-between gap-2 mb-4">
              <code className="text-xs">npx skills add mateffy/laraperf</code>
              <button
                onClick={() => { navigator.clipboard?.writeText('npx skills add mateffy/laraperf'); setCopiedSkill(true); setTimeout(() => setCopiedSkill(false), 2000) }}
                className="shrink-0 text-stone-500 hover:text-stone-300 transition"
                title="Copy command"
              >
                {copiedSkill ? <Check size={12} /> : <Copy size={12} />}
              </button>
            </div>
            <p className="text-emerald-200/60 text-sm mb-3">
              Or paste this prompt for a quick one-shot:
            </p>
            <div className="relative">
              <textarea
                readOnly
                value={promptText}
                className="w-full h-36 bg-emerald-900/50 border border-emerald-800 text-emerald-100 text-xs font-mono p-3 leading-relaxed resize-none focus:outline-none rounded-lg"
              />
              <button
                onClick={() => { navigator.clipboard?.writeText(promptText); setCopiedPrompt(true); setTimeout(() => setCopiedPrompt(false), 2000) }}
                className="absolute top-2 right-2 p-1.5 bg-emerald-800 hover:bg-emerald-700 text-emerald-200 transition rounded"
                title="Copy prompt"
              >
                {copiedPrompt ? <Check size={14} /> : <Copy size={14} />}
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

// Main block renderer
function renderBlock(block, eyebrow, post) {
  const blockRenderers = {
    hero: HeroBlock,
    text: TextBlock,
    visualization: VisualizationBlock,
    command: CommandBlock,
    fix: FixBlock,
    features: FeaturesBlock,
    cta: CTABlock,
    diagnostic: DiagnosticBlock,
    "before-after": BeforeAfterBlock,
    workflow: WorkflowBlock,
    tip: TipBlock,
    comparison: ComparisonBlock,
    session: SessionBlock,
    integration: IntegrationBlock,
    yaml: YamlBlock,
    safety: SafetyBlock,
    "best-practices": BestPracticesBlock,
    insights: InsightsBlock,
    "multi-tenant": MultiTenantBlock,
    cleanup: CleanupBlock,
    "install-cta": InstallCTABlock
  }

  const Renderer = blockRenderers[block.type]
  if (!Renderer) return null

  return <Renderer block={block} eyebrow={eyebrow} post={block.type === 'hero' ? post : undefined} />
}

function BlogPost() {
  const { post } = Route.useLoaderData()

  return (
    <>
      {/* ── CONTENT BLOCKS ── */}
      {post.blocks.map((block, index) => (
        <div key={index}>
          {renderBlock(block, post.eyebrow, post)}
        </div>
      ))}

      {/* ── POST FOOTER ── */}
      <section className="py-12 px-4 md:px-8 border-b border-stone-200 bg-stone-50">
        <div className="max-w-6xl mx-auto flex items-center justify-between">
          <Link
            to="/blog"
            className="inline-flex items-center gap-2 text-stone-500 hover:text-emerald-700 transition font-medium"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="19" y1="12" x2="5" y2="12" />
              <polyline points="12 19 5 12 12 5" />
            </svg>
            All articles
          </Link>
          <Link
            to="/"
            className="inline-flex items-center gap-2 text-stone-500 hover:text-emerald-700 transition font-medium"
          >
            Home
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </Link>
        </div>
      </section>

      {/* ── FOOTER ── */}
      <footer className="pt-16 pb-10 px-4 md:px-12">
        <div className="max-w-6xl mx-auto">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 pb-12">
            <div className="lg:col-span-2">
              <div className="flex items-center gap-2 text-emerald-900 font-bold text-xl mb-4 font-outfit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
                laraperf
              </div>
              <p className="text-stone-500 text-sm max-w-xs leading-relaxed">
                A <strong className="text-stone-700">Laravel performance CLI</strong> purpose-built for LLM coding agents. MIT License.
              </p>
            </div>
            <div>
              <h4 className="font-bold text-stone-900 mb-6 font-outfit">Package</h4>
              <ul className="space-y-3 text-sm text-stone-500">
                <li><a href="https://github.com/mateffy/laraperf" target="_blank" rel="noopener noreferrer" className="hover:text-emerald-700 transition">GitHub</a></li>
                <li><a href="https://packagist.org/packages/mateffy/laraperf" target="_blank" rel="noopener noreferrer" className="hover:text-emerald-700 transition">Packagist</a></li>
                <li><a href="https://github.com/mateffy/laraperf/blob/main/CHANGELOG.md" target="_blank" rel="noopener noreferrer" className="hover:text-emerald-700 transition">Changelog</a></li>
                <li><a href="https://github.com/mateffy/laraperf/blob/main/LICENSE.md" target="_blank" rel="noopener noreferrer" className="hover:text-emerald-700 transition">License</a></li>
              </ul>
            </div>
            <div>
              <h4 className="font-bold text-stone-900 mb-6 font-outfit">Commands</h4>
              <ul className="space-y-3 text-sm text-stone-500 font-mono">
                <li><a href="/#commands" className="hover:text-emerald-700 transition">perf:watch</a></li>
                <li><a href="/#commands" className="hover:text-emerald-700 transition">perf:query</a></li>
                <li><a href="/#commands" className="hover:text-emerald-700 transition">perf:explain</a></li>
                <li><a href="/#commands" className="hover:text-emerald-700 transition">perf:stop</a></li>
                <li><a href="/#commands" className="hover:text-emerald-700 transition">perf:clear</a></li>
              </ul>
            </div>
          </div>
          <div className="border-t border-stone-200 pt-8 text-xs text-stone-400 flex flex-col md:flex-row items-center justify-between gap-4">
            <span>© 2026 laraperf · MIT License</span>
            <a href="https://github.com/mateffy/laraperf" target="_blank" rel="noopener noreferrer" className="text-stone-400 hover:text-emerald-700 transition">
              mateffy/laraperf
            </a>
          </div>
        </div>
      </footer>
    </>
  )
}
