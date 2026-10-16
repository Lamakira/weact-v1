// Test de charge k6 : parcours d'un visiteur non connecté (pages et API publiques).
//
// Lancer :
//   k6 run -e BASE_URL=https://weact.bj -e PROFILE=smoke loadtest/public-visitor.js
//   k6 run -e BASE_URL=https://weact.bj -e PROFILE=ramp  loadtest/public-visitor.js
//
// PROFILE=ramp suppose que l'IP de la machine de test figure dans THROTTLE_BYPASS_IPS
// côté serveur (sinon l'API répond 429 au-delà de 60 requêtes/minute et on mesure la limite,
// pas la capacité). Le test s'arrête seul si les erreurs ou la latence dérapent.
import http from 'k6/http'
import { check, group, sleep } from 'k6'

const BASE_URL = __ENV.BASE_URL || 'https://weact.bj'
const API = `${BASE_URL}/api/v1/public`
const PROFILE = __ENV.PROFILE || 'smoke'

const profiles = {
  // Validation du script : 1 visiteur, sous la limite de 60 requêtes/minute.
  smoke: {
    executor: 'constant-vus',
    vus: 1,
    duration: '1m',
  },
  // Montée par paliers en visites par seconde (une visite ≈ 6 requêtes) jusqu'à la rupture.
  ramp: {
    executor: 'ramping-arrival-rate',
    startRate: 1,
    timeUnit: '1s',
    preAllocatedVUs: 50,
    maxVUs: 1500,
    stages: [
      { target: 2, duration: '1m' },
      { target: 5, duration: '2m' },
      { target: 10, duration: '2m' },
      { target: 20, duration: '2m' },
      { target: 30, duration: '2m' },
      { target: 45, duration: '2m' },
      { target: 60, duration: '2m' },
      { target: 80, duration: '2m' },
      { target: 0, duration: '30s' },
    ],
  },
}

export const options = {
  scenarios: { visitor: profiles[PROFILE] },
  thresholds: {
    // Arrêt automatique : plus de 5 % d'erreurs ou p95 au-delà de 4 s, après 30 s de mesure.
    http_req_failed: [{ threshold: 'rate<0.05', abortOnFail: true, delayAbortEval: '30s' }],
    http_req_duration: [
      'p(95)<800',
      { threshold: 'p(95)<4000', abortOnFail: true, delayAbortEval: '30s' },
    ],
    'http_req_duration{type:api}': ['p(95)<800'],
    'http_req_duration{type:page}': ['p(95)<500'],
  },
  summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
}

// Récupère une fois des pseudos de Faces et des slugs de missions réels pour varier les fiches.
export function setup() {
  const faces = http.get(`${API}/faces?page=1&per_page=24`, { headers: { Accept: 'application/json' } })
  const missions = http.get(`${API}/missions?page=1`, { headers: { Accept: 'application/json' } })
  const usernames = (faces.json('data') || []).map((f) => f.username).filter(Boolean)
  const slugs = (missions.json('data') || []).map((m) => m.slug).filter(Boolean)
  return { usernames, slugs }
}

const json = { headers: { Accept: 'application/json' }, tags: { type: 'api' } }
const pick = (list) => list[Math.floor(Math.random() * list.length)]

export default function (data) {
  group('accueil', () => {
    const res = http.get(`${BASE_URL}/`, { tags: { type: 'page' } })
    check(res, { 'accueil 200': (r) => r.status === 200 })
    http.get(`${API}/missions?page=1`, json)
    http.get(`${API}/faces?page=1&per_page=12`, json)
  })

  group('annuaire des Faces', () => {
    const page = 1 + Math.floor(Math.random() * 5)
    const res = http.get(`${API}/faces?page=${page}&per_page=24`, json)
    check(res, { 'liste Faces 200': (r) => r.status === 200 })
    http.get(`${API}/faces/options`, json)
  })

  if (data.usernames.length > 0) {
    group('fiche Face', () => {
      const res = http.get(`${API}/faces/${pick(data.usernames)}`, json)
      check(res, { 'fiche Face 200': (r) => r.status === 200 })
    })
  }

  if (data.slugs.length > 0 && Math.random() < 0.3) {
    group('fiche mission', () => {
      http.get(`${API}/missions/${pick(data.slugs)}`, json)
    })
  }

  // En smoke : rester sous 60 requêtes/minute ; en rampe le rythme est imposé par l'exécuteur.
  if (PROFILE === 'smoke') sleep(8)
}
