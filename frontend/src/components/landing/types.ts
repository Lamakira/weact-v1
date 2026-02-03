import type { Component } from 'vue'

export type Perspective = 'face' | 'producer'

export interface Step {
  number: string
  icon: Component
  title: string
  description: string
}

export interface Feature {
  icon: Component
  title: string
  description: string
}

export interface Mission {
  id: number
  type: string
  typeColor: string
  title: string
  description: string
  price: string
  location: string
  status: 'available' | 'urgent' | 'in_progress'
  img: string
}

export interface LandingContent {
  howItWorks: {
    title: string
    subtitle: string
    steps: Step[]
  }
  missions: {
    title: string
    subtitle: string
    ctaText: string
    ctaLink: string
  }
  whyWeact: {
    title: string
    subtitle: string
    features: Feature[]
    certifiedText: string
    footerText: string
  }
  finalCta: {
    title: string
    subtitle: string
    buttonText: string
    buttonLink: string
  }
}
