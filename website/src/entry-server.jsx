import React from 'react'
import { renderToString } from 'react-dom/server'
import { createMemoryHistory, createRouter, RouterProvider } from '@tanstack/react-router'
import { routeTree } from './routeTree.gen'

export async function render(url) {
  const history = createMemoryHistory({
    initialEntries: [url],
  })

  const router = createRouter({
    routeTree,
    history,
    defaultPreload: 'intent',
    scrollRestoration: true,
  })

  await router.load()

  const html = renderToString(
    <RouterProvider router={router} />,
  )

  return { html }
}