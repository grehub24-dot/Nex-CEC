/**
 * Nex CEC — School 3D Elements Module
 * Shared Three.js scenes for school-appropriate decorative 3D elements
 *
 * Scene types:
 *   'school'    — Purple school building with cone roof, windows, door, floating books
 *   'books'     — Stacked books with grad cap on top
 *   'envelope'  — Envelope with flap and seal
 *   'frame'     — Picture frame with inner mat and photo
 *   'calendar'  — Calendar with pages, grid, and spiral rings
 *
 * Usage:
 *   import { initScene } from './school-3d.js';
 *   initScene('hero-3d-container', 'school');
 */
import * as THREE from 'three';

/**
 * Detect WebGL support
 * @returns {boolean}
 */
export function detectWebGL() {
    try {
        const canvas = document.createElement('canvas');
        return !!(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'));
    } catch (e) {
        return false;
    }
}

/**
 * Create a Three.js renderer attached to a container element.
 * @param {HTMLElement} container
 * @returns {{ renderer: THREE.WebGLRenderer, width: number, height: number }|null}
 */
export function createRenderer(container) {
    const width = container.offsetWidth || 400;
    const height = container.offsetHeight || 300;
    if (width < 100 || height < 100) return null;

    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);
    return { renderer, width, height };
}

/**
 * Setup basic scene lighting
 * @param {THREE.Scene} scene
 */
export function addSceneLights(scene) {
    const ambient = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambient);
    const dir = new THREE.DirectionalLight(0xffffff, 0.8);
    dir.position.set(3, 5, 4);
    scene.add(dir);
    const fill = new THREE.DirectionalLight(0x8888ff, 0.3);
    fill.position.set(-3, 2, -3);
    scene.add(fill);
}

/**
 * Handle resize for a 3D scene
 * @param {HTMLElement} container
 * @param {THREE.PerspectiveCamera} camera
 * @param {THREE.WebGLRenderer} renderer
 */
export function addResizeHandler(container, camera, renderer) {
    window.addEventListener('resize', function () {
        const w = container.offsetWidth || 400;
        const h = container.offsetHeight || 300;
        if (w < 100 || h < 100) return;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h);
    });
}

// =========================================================================
// Scene Builders
// =========================================================================

/**
 * Build a school building scene
 * @param {string} containerId - DOM element ID
 * @returns {function|null} Cleanup function, or null if failed
 */
export function buildSchoolScene(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return null;
    if (!detectWebGL()) {
        container.classList.add('no-webgl');
        return null;
    }

    const setup = createRenderer(container);
    if (!setup) return null;
    const { renderer, width, height } = setup;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 1000);
    camera.position.set(3, 1.5, 4);
    camera.lookAt(0, 0, 0);

    addSceneLights(scene);

    // Main building block
    const buildingMat = new THREE.MeshPhongMaterial({
        color: 0x5645d4,
        emissive: 0x2a1a7a,
        emissiveIntensity: 0.1,
        shininess: 30,
    });
    const building = new THREE.Mesh(new THREE.BoxGeometry(1.6, 1.2, 1.0), buildingMat);
    building.position.y = 0.6;
    building.castShadow = true;
    scene.add(building);

    // Pyramid roof
    const roofMat = new THREE.MeshPhongMaterial({ color: 0x0a1530, shininess: 10 });
    const roof = new THREE.Mesh(new THREE.ConeGeometry(1.1, 0.5, 4), roofMat);
    roof.position.y = 1.45;
    roof.rotation.y = Math.PI / 4;
    roof.castShadow = true;
    scene.add(roof);

    // Windows
    const windowMat = new THREE.MeshPhongMaterial({
        color: 0xffe8d4,
        emissive: 0xffcc80,
        emissiveIntensity: 0.3,
    });
    for (let i = -0.5; i <= 0.5; i += 0.5) {
        const w1 = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.05), windowMat);
        w1.position.set(i, 0.65, 0.51);
        scene.add(w1);
        const w2 = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.05), windowMat);
        w2.position.set(i, 0.65, -0.51);
        scene.add(w2);
    }

    // Door
    const doorMat = new THREE.MeshPhongMaterial({ color: 0x1a2a52 });
    const door = new THREE.Mesh(new THREE.BoxGeometry(0.25, 0.4, 0.05), doorMat);
    door.position.set(0, 0.2, 0.51);
    scene.add(door);

    // Ground
    const groundMat = new THREE.MeshPhongMaterial({ color: 0x1a2a52, transparent: true, opacity: 0.3 });
    const ground = new THREE.Mesh(new THREE.CircleGeometry(2.5, 32), groundMat);
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.01;
    ground.receiveShadow = true;
    scene.add(ground);

    // Floating books
    const bookColors = [0xd9f3e1, 0xe6e0f5, 0xffe8d4];
    const books = [];
    for (let i = 0; i < 3; i++) {
        const book = new THREE.Mesh(
            new THREE.BoxGeometry(0.25 + i * 0.05, 0.05, 0.18 + i * 0.02),
            new THREE.MeshPhongMaterial({ color: bookColors[i] })
        );
        book.position.set(-1.3 + i * 0.1, 1.0 + i * 0.1, 0.6 - i * 0.05);
        book.rotation.z = (i - 1) * 0.1;
        scene.add(book);
        books.push(book);
    }

    // Star particles
    const starMat = new THREE.PointsMaterial({ color: 0xffffff, size: 0.02, transparent: true, opacity: 0.4 });
    const positions = [];
    for (let i = 0; i < 60; i++) {
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        const r = 2.5 + Math.random() * 1.5;
        positions.push(r * Math.sin(phi) * Math.cos(theta), r * Math.cos(phi) * 0.5 + 0.5, r * Math.sin(phi) * Math.sin(theta));
    }
    const starGeo = new THREE.BufferGeometry();
    starGeo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    const stars = new THREE.Points(starGeo, starMat);
    scene.add(stars);

    // Animation loop
    let running = true;
    function animate() {
        if (!running) return;
        requestAnimationFrame(animate);
        building.rotation.y += 0.005;
        roof.rotation.y += 0.005;
        books.forEach(function (b) { b.rotation.y += 0.003; });
        stars.rotation.y -= 0.001;
        renderer.render(scene, camera);
    }
    animate();

    addResizeHandler(container, camera, renderer);

    return function cleanup() {
        running = false;
        renderer.dispose();
    };
}

/**
 * Build a stacked books with grad cap scene
 * @param {string} containerId
 * @returns {function|null}
 */
export function buildBooksScene(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return null;
    if (!detectWebGL()) { container.classList.add('no-webgl'); return null; }

    const setup = createRenderer(container);
    if (!setup) return null;
    const { renderer, width, height } = setup;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(40, width / height, 0.1, 1000);
    camera.position.set(2, 1.5, 3);
    addSceneLights(scene);

    // Stacked books
    const colors = [0xe6e0f5, 0xd9f3e1, 0xffe8d4, 0xdcecfa];
    for (let i = 0; i < 4; i++) {
        const book = new THREE.Mesh(
            new THREE.BoxGeometry(0.8 - i * 0.08, 0.1, 0.5 - i * 0.05),
            new THREE.MeshPhongMaterial({ color: colors[i] })
        );
        book.position.set(0, i * 0.12, 0);
        book.rotation.z = (i - 1.5) * 0.06;
        scene.add(book);
    }

    // Grad cap
    const capMat = new THREE.MeshPhongMaterial({ color: 0x5645d4 });
    const capBase = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.03, 0.5), capMat);
    capBase.position.set(0, 0.55, 0);
    scene.add(capBase);
    const capTop = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.06, 0.08), capMat);
    capTop.position.set(0, 0.6, 0);
    scene.add(capTop);

    let running = true;
    function animate() {
        if (!running) return;
        requestAnimationFrame(animate);
        scene.rotation.y += 0.008;
        renderer.render(scene, camera);
    }
    animate();

    addResizeHandler(container, camera, renderer);
    return function cleanup() { running = false; renderer.dispose(); };
}

/**
 * Build an envelope scene with floating animation
 * @param {string} containerId
 * @returns {function|null}
 */
export function buildEnvelopeScene(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return null;
    if (!detectWebGL()) { container.classList.add('no-webgl'); return null; }

    const setup = createRenderer(container);
    if (!setup) return null;
    const { renderer, width, height } = setup;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 1000);
    camera.position.set(2, 1.5, 3);
    addSceneLights(scene);

    // Envelope body
    const bodyMat = new THREE.MeshPhongMaterial({ color: 0x5645d4 });
    const body = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.7, 0.05), bodyMat);
    body.position.y = 0;
    scene.add(body);

    // Flap (triangle)
    const flapMat = new THREE.MeshPhongMaterial({ color: 0x4534b3 });
    const flapGeo = new THREE.BufferGeometry();
    const verts = new Float32Array([
        0, 0.35, 0.03,   -0.6, -0.35, 0.03,   0, -0.05, 0.03,
        0, 0.35, 0.03,   0.6, -0.35, 0.03,   0, -0.05, 0.03,
    ]);
    flapGeo.setAttribute('position', new THREE.BufferAttribute(verts, 3));
    flapGeo.computeVertexNormals();
    const flap = new THREE.Mesh(flapGeo, flapMat);
    flap.position.y = 0;
    scene.add(flap);

    // Seal dot
    const sealMat = new THREE.MeshPhongMaterial({ color: 0xffe8d4 });
    const seal = new THREE.Mesh(new THREE.SphereGeometry(0.06, 8, 8), sealMat);
    seal.position.set(0, 0.32, 0.04);
    scene.add(seal);

    let running = true;
    let time = 0;
    function animate() {
        if (!running) return;
        requestAnimationFrame(animate);
        time += 0.02;
        body.position.y = Math.sin(time) * 0.08;
        flap.position.y = Math.sin(time) * 0.08;
        seal.position.y = Math.sin(time) * 0.08 + 0.32;
        scene.rotation.y += 0.005;
        renderer.render(scene, camera);
    }
    animate();

    addResizeHandler(container, camera, renderer);
    return function cleanup() { running = false; renderer.dispose(); };
}

/**
 * Build a picture frame scene with rotation
 * @param {string} containerId
 * @returns {function|null}
 */
export function buildFrameScene(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return null;
    if (!detectWebGL()) { container.classList.add('no-webgl'); return null; }

    const setup = createRenderer(container);
    if (!setup) return null;
    const { renderer, width, height } = setup;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 1000);
    camera.position.set(2, 1.5, 3);
    addSceneLights(scene);

    // Frame (hollow box)
    const frameMat = new THREE.MeshPhongMaterial({ color: 0x5645d4 });
    const frame = new THREE.Mesh(new THREE.BoxGeometry(1.2, 1.0, 0.06), frameMat);
    frame.position.y = 0;
    scene.add(frame);

    // Inner mat (slightly smaller, lighter)
    const matMat = new THREE.MeshPhongMaterial({ color: 0xd9f3e1 });
    const mat = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.7, 0.065), matMat);
    mat.position.set(0, 0, 0.01);
    scene.add(mat);

    // Photo plane
    const photoMat = new THREE.MeshPhongMaterial({
        color: 0xffffff,
        emissive: 0xe6e0f5,
        emissiveIntensity: 0.2,
    });
    const photo = new THREE.Mesh(new THREE.PlaneGeometry(0.8, 0.6), photoMat);
    photo.position.set(0, 0, 0.035);
    scene.add(photo);

    // Corner dots
    const dotMat = new THREE.MeshPhongMaterial({ color: 0x0a1530 });
    const corners = [[-0.55, 0.45], [0.55, 0.45], [-0.55, -0.45], [0.55, -0.45]];
    corners.forEach(function (pos) {
        const dot = new THREE.Mesh(new THREE.SphereGeometry(0.04, 6, 6), dotMat);
        dot.position.set(pos[0], pos[1], 0.035);
        scene.add(dot);
    });

    let running = true;
    function animate() {
        if (!running) return;
        requestAnimationFrame(animate);
        scene.rotation.y += 0.008;
        renderer.render(scene, camera);
    }
    animate();

    addResizeHandler(container, camera, renderer);
    return function cleanup() { running = false; renderer.dispose(); };
}

/**
 * Build a calendar scene with rotation
 * @param {string} containerId
 * @returns {function|null}
 */
export function buildCalendarScene(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return null;
    if (!detectWebGL()) { container.classList.add('no-webgl'); return null; }

    const setup = createRenderer(container);
    if (!setup) return null;
    const { renderer, width, height } = setup;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 1000);
    camera.position.set(2.5, 1.5, 3);
    addSceneLights(scene);

    // Calendar body
    const bodyMat = new THREE.MeshPhongMaterial({ color: 0x5645d4 });
    const body = new THREE.Mesh(new THREE.BoxGeometry(1.6, 1.2, 0.15), bodyMat);
    body.position.y = 0;
    scene.add(body);

    // White pages
    const pageMat = new THREE.MeshPhongMaterial({ color: 0xffffff });
    const page1 = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.9, 0.02), pageMat);
    page1.position.set(0, -0.05, 0.1);
    scene.add(page1);
    const page2 = new THREE.Mesh(new THREE.BoxGeometry(1.18, 0.88, 0.02), pageMat);
    page2.position.set(0.01, -0.04, 0.12);
    scene.add(page2);

    // Grid cells (4x5)
    const cellMat = new THREE.MeshPhongMaterial({ color: 0xe6e0f5 });
    for (let row = 0; row < 5; row++) {
        for (let col = 0; col < 4; col++) {
            const cell = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.1, 0.01), cellMat);
            cell.position.set(-0.45 + col * 0.3, 0.2 - row * 0.18, 0.11);
            scene.add(cell);
        }
    }

    // Spiral rings
    const ringMat = new THREE.MeshPhongMaterial({ color: 0x787671 });
    for (let i = 0; i < 3; i++) {
        const ring = new THREE.Mesh(new THREE.TorusGeometry(0.06, 0.015, 6, 8), ringMat);
        ring.position.set(-0.5 + i * 0.5, 0.62, 0);
        ring.rotation.x = Math.PI / 2;
        scene.add(ring);
    }

    let running = true;
    function animate() {
        if (!running) return;
        requestAnimationFrame(animate);
        scene.rotation.y += 0.008;
        renderer.render(scene, camera);
    }
    animate();

    addResizeHandler(container, camera, renderer);
    return function cleanup() { running = false; renderer.dispose(); };
}

/**
 * Initialize a scene by type
 * @param {string} containerId
 * @param {'school'|'books'|'envelope'|'frame'|'calendar'} sceneType
 * @returns {function|null}
 */
export function initScene(containerId, sceneType) {
    switch (sceneType) {
        case 'school':    return buildSchoolScene(containerId);
        case 'books':     return buildBooksScene(containerId);
        case 'envelope':  return buildEnvelopeScene(containerId);
        case 'frame':     return buildFrameScene(containerId);
        case 'calendar':  return buildCalendarScene(containerId);
        default:
            console.warn('Unknown 3D scene type:', sceneType);
            return null;
    }
}
