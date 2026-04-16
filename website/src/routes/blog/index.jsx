import { createFileRoute, Link } from '@tanstack/react-router'
import { getAllPosts } from '@/data/posts'

export const Route = createFileRoute('/blog/')({
  component: BlogIndex,
  loader: async () => {
    return {
      posts: getAllPosts()
    }
  },
  head: () => ({
    meta: [
      { title: 'Blog — laraperf' },
      { name: 'description', content: 'Articles about Laravel performance, LLM coding agents, and database optimization.' },
    ],
  }),
})

function BlogIndex() {
  const { posts } = Route.useLoaderData()

  return (
    <>
      {/* ── BLOG HEADER ── */}
      <section className="relative pt-20 pb-12 text-center overflow-hidden border-b border-stone-200">
        <h1 className="text-4xl md:text-6xl font-bold text-stone-900 leading-tight px-4 font-outfit">
          Blog
        </h1>
        <p className="mt-6 text-lg md:text-xl text-stone-500 max-w-2xl mx-auto px-4">
          Articles about Laravel performance, LLM coding agents, and database optimization.
        </p>
      </section>

      {/* ── BLOG LIST ── */}
      <section className="border-b border-stone-200">
        <div className="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-stone-200">
          {posts.map((post, index) => (
            <article
              key={post.slug}
              className={`group ${index % 4 === 0 || index % 4 === 3 ? 'bg-stone-950/60' : 'bg-stone-50'} transition-colors`}
            >
              <Link to="/blog/$slug" params={{ slug: post.slug }} className="block p-8 lg:p-12 h-full">
                {/* Tags */}
                <div className="flex flex-wrap gap-2 mb-6">
                  {post.tags.map((tag) => (
                    <span
                      key={tag}
                      className={`text-xs font-medium px-2 py-1 border ${
                        index % 4 === 0 || index % 4 === 3
                          ? 'text-emerald-400 border-emerald-900 bg-emerald-950/50'
                          : 'text-emerald-700 border-emerald-200 bg-emerald-50'
                      }`}
                    >
                      {tag}
                    </span>
                  ))}
                </div>
                
                {/* Title */}
                <h2 className={`text-2xl lg:text-3xl font-bold font-outfit group-hover:text-emerald-600 transition-colors mb-4 leading-tight ${
                  index % 4 === 0 || index % 4 === 3 ? 'text-stone-50' : 'text-stone-900'
                }`}>
                  {post.title}
                </h2>
                
                {/* Description */}
                <p className={`leading-relaxed mb-6 ${
                  index % 4 === 0 || index % 4 === 3 ? 'text-stone-300' : 'text-stone-500'
                }`}>
                  {post.description}
                </p>
                
                {/* Meta */}
                <div className={`flex items-center gap-6 text-sm ${
                  index % 4 === 0 || index % 4 === 3 ? 'text-stone-400' : 'text-stone-400'
                }`}>
                  <span className="flex items-center gap-2">
                    <svg
                      width="14"
                      height="14"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    >
                      <rect x="3" y="4" width="18" height="18" />
                      <line x1="16" y1="2" x2="16" y2="6" />
                      <line x1="8" y1="2" x2="8" y2="6" />
                      <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    {new Date(post.date).toLocaleDateString('en-US', {
                      year: 'numeric',
                      month: 'short',
                      day: 'numeric',
                    })}
                  </span>
                  <span className="flex items-center gap-2">
                    <svg
                      width="14"
                      height="14"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    >
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </svg>
                    {post.readingTime}
                  </span>
                </div>

                {/* Read more */}
                <div className={`mt-8 flex items-center gap-2 text-sm font-medium ${
                  index % 4 === 0 || index % 4 === 3
                    ? 'text-emerald-400 group-hover:text-emerald-300'
                    : 'text-emerald-600 group-hover:text-emerald-700'
                } transition-colors`}>
                  Read article
                  <svg
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="group-hover:translate-x-1 transition-transform"
                  >
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                  </svg>
                </div>
              </Link>
            </article>
          ))}
        </div>
      </section>

      {/* ── FOOTER ── */}
      <footer className="pt-16 pb-10 px-4 md:px-12">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 pb-12">
          <div className="lg:col-span-2">
            <div className="flex items-center gap-2 text-emerald-900 font-bold text-xl mb-4 font-outfit">
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="text-emerald-600"
              >
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
              </svg>
              laraperf
            </div>
            <p className="text-stone-500 text-sm max-w-xs leading-relaxed">
              A{' '}
              <strong className="text-stone-700">
                Laravel performance CLI
              </strong>{' '}
              purpose-built for LLM coding agents. MIT License.
            </p>
            <div className="flex gap-4 mt-6 text-stone-400">
              <a
                href="https://github.com/mateffy/laraperf"
                target="_blank"
                rel="noopener noreferrer"
                className="hover:text-emerald-600 transition"
              >
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                </svg>
              </a>
            </div>
          </div>
          <div>
            <h4 className="font-bold text-stone-900 mb-6 font-outfit">
              Package
            </h4>
            <ul className="space-y-3 text-sm text-stone-500">
              <li>
                <a
                  href="https://github.com/mateffy/laraperf"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  GitHub
                </a>
              </li>
              <li>
                <a
                  href="https://packagist.org/packages/mateffy/laraperf"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  Packagist
                </a>
              </li>
              <li>
                <a
                  href="https://github.com/mateffy/laraperf/blob/main/CHANGELOG.md"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  Changelog
                </a>
              </li>
              <li>
                <a
                  href="https://github.com/mateffy/laraperf/blob/main/LICENSE.md"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  License
                </a>
              </li>
            </ul>
          </div>
          <div>
            <h4 className="font-bold text-stone-900 mb-6 font-outfit">
              Commands
            </h4>
            <ul className="space-y-3 text-sm text-stone-500 font-mono">
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:watch
                </a>
              </li>
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:query
                </a>
              </li>
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:explain
                </a>
              </li>
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:stop
                </a>
              </li>
              <li>
                <a
                  href="/#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:clear
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div className="border-t border-stone-200 pt-8 text-xs text-stone-400 flex flex-col md:flex-row items-center justify-between gap-4">
          <span>© 2026 laraperf · MIT License</span>
          <a
            href="https://github.com/mateffy/laraperf"
            target="_blank"
            rel="noopener noreferrer"
            className="text-stone-400 hover:text-emerald-700 transition"
          >
            mateffy/laraperf
          </a>
        </div>
      </footer>
    </>
  )
}
