const localVideo = document.getElementById('localVideo');
const remoteVideo = document.getElementById('remoteVideo');

let localStream;
let peerConnection;
const config = {
    iceServers: [{urls: 'stun:stun.l.google.com:19302'}] // Google's public STUN server
};

// Initialize WebSocket connection
const socket = new WebSocket('wss://cam.afikim.pro:8080');
socket.addEventListener('open', function(event) {
    // Send identification message
    const params = new URLSearchParams(window.location.search);
    const user_guid = params.get('user_guid'); // This gets '1' from your example URL
    const room_guid = params.get('room_guid');
    const user_type = params.get('user_type');
    socket.send(JSON.stringify({ type: 'identify', user_guid: user_guid ,room_guid:room_guid,user_type:user_type}));
});

// Handle WebSocket messages
socket.addEventListener('message', async function (event) {
    const data = JSON.parse(event.data);
    if (data.type === 'offer') {
        await createPeerConnection();
        await peerConnection.setRemoteDescription(new RTCSessionDescription(data.data));
        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);
        const params = new URLSearchParams(window.location.search);
        const user_guid = params.get('user_guid'); // This gets '1' from your example URL
        const room_guid = params.get('room_guid');
        const user_type = params.get('user_type');
        socket.send(JSON.stringify({type: 'answer', user_guid: user_guid ,room_guid:room_guid,user_type:user_type,data: answer}));
        //socket.send(JSON.stringify({type: 'answer', answer: answer}));
    } else if (data.type === 'answer') {
        await peerConnection.setRemoteDescription(new RTCSessionDescription(data.data));
    } else if (data.type === 'candidate') {
        await peerConnection.addIceCandidate(new RTCIceCandidate(data.data));
    }

  

});
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('startCall').addEventListener('click', function() {
        startCall();
    });
});


async function createPeerConnection() {
    peerConnection = new RTCPeerConnection(config);

    // ICE candidate event: send any ICE candidates to the remote peer
    peerConnection.onicecandidate = function(event) {
        if (event.candidate) {
            const params = new URLSearchParams(window.location.search);
            const user_guid = params.get('user_guid'); // This gets '1' from your example URL
            const room_guid = params.get('room_guid');
            const user_type = params.get('user_type');
            socket.send(JSON.stringify({type: 'candidate', user_guid: user_guid ,room_guid:room_guid,user_type:user_type,data: event.candidate}));
            //socket.send(JSON.stringify({type: 'candidate', candidate: event.candidate}));
        }
    };

    // Stream event: set the remote video source when a stream is received from the remote peer
    peerConnection.ontrack = function(event) {
        remoteVideo.srcObject = event.streams[0];
    };

    // Get local media stream
    if (!localStream) {
        try {
            localStream = await navigator.mediaDevices.getUserMedia({video: true, audio: true});
            localVideo.srcObject = localStream;
        } catch (error) {
            console.error('getUserMedia error:', error);
            return;
        }
    }

    // Add each track from the local stream to the peer connection
    localStream.getTracks().forEach(track => {
        peerConnection.addTrack(track, localStream);
    });
}


// Starting a call (example)
async function startCall() {
    await createPeerConnection();
    const offer = await peerConnection.createOffer();
    await peerConnection.setLocalDescription(offer);

    // Extract user_guid from the URL
    const params = new URLSearchParams(window.location.search);
    const user_guid = params.get('user_guid'); // This gets '1' from your example URL
    const room_guid = params.get('room_guid');
    const user_type = params.get('user_type');
    socket.send(JSON.stringify({type: 'offer', user_guid: user_guid ,room_guid:room_guid,user_type:user_type,data: offer}));

    // // Include user_guid in the WebSocket message
    // socket.send(JSON.stringify({
    //     type: 'offer',
    //     offer: offer,
    //     from: user_guid
    //      // Include the extracted user_guid
    // }));

}

// Accepting a call would be handled within the 'message' event listener for 'offer' type
