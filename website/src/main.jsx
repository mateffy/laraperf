import React from 'react'
import ReactDOM from 'react-dom/client'
import { RouterProvider, createRouter } from '@tanstack/react-router'
import './index.css'
import { Agentation } from 'agentation'

import { routeTree } from './routeTree.gen'

const router = createRouter({ 
  routeTree,
  defaultPreload: 'intent',
  scrollRestoration: true,
})

const rootElement = document.getElementById('root')

if (rootElement) {
  if (rootElement.innerHTML) {
    ReactDOM.hydrateRoot(
      rootElement,
      <React.StrictMode>
        <RouterProvider router={router} />
        {import.meta.env.DEV && <Agentation />}
      </React.StrictMode>,
    )
  } else {
    ReactDOM.createRoot(rootElement).render(
      <React.StrictMode>
        <RouterProvider router={router} />
        {import.meta.env.DEV && <Agentation />}
      </React.StrictMode>,
    )
  }
}