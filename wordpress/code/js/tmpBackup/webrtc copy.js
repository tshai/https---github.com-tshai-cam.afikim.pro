let localVideo = document.getElementById("localVideo"),remoteVideo = document.getElementById("remoteVideo");
let params = new URLSearchParams(window.location.search);
let user_guid = params.get("user_guid"),room_guid = params.get("room_guid"),user_type = params.get("user_type");
let localStream,peerConnection;
//let userStartChat = 0;
const config = {
  iceServers: [{ urls: "stun:stun.l.google.com:19302" }], // Google's public STUN server
};

// Initialize WebSocket connection
const socket = new WebSocket("wss://cam.afikim.pro:8080");
$(document).ready(function () {
   localVideo = document.getElementById("localVideo");
   remoteVideo = document.getElementById("remoteVideo");
    navigator.mediaDevices
      .getUserMedia({ video: true, audio: true })
      .then(function (localStream) {
        // Assuming localVideo is a previously selected DOM element
        localVideo.srcObject = localStream;
        $("#startCall").show();
      })
      .catch(function (error) {
        alert("לא ניתן לגשת למצלמה או למיקרופון, אנא נסה לפתוח את האתר מחדש");
        console.error("getUserMedia error:", error);
      });
 
});
socket.addEventListener("open", function (event) {
    socket.send(
      JSON.stringify({
        type: "identify",
        user_guid: user_guid,
        room_guid: room_guid,
        user_type: user_type,
      })
    );
});
async function addVideoToCall() {
  try {
      // Request access to the user's video, without disrupting the existing audio stream.
      const videoStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      const videoTrack = videoStream.getVideoTracks()[0];

      // Add the video track to the peer connection.
      if (videoTrack) {
          peerConnection.addTrack(videoTrack, videoStream);
      }

      // Create a new offer since the media content of the peer connection has changed.
      const offer = await peerConnection.createOffer();
      await peerConnection.setLocalDescription(offer);

      // Send the new offer to the remote peer through your signaling channel.
      // You will need to implement the signalingChannel.send function based on your application's specific signaling mechanism.
      //signalingChannel.send({ type: 'offer', offer: peerConnection.localDescription });
      socket.send(
        JSON.stringify({
          type: "offer",
          user_guid: user_guid,
          room_guid: room_guid,
          user_type: user_type,
          data: offer,
        })
      );
      // Listen for a response from the remote peer (an answer) and set it as the remote description.
      // This part should be handled in your signaling channel's message event listener,
      // similar to the setup in the initial connection process.

  } catch (error) {
      console.error("Error adding video to call:", error);
  }
}

// Handle WebSocket messages
socket.addEventListener("message", async function (event) {
    const data = JSON.parse(event.data);
    if (typeof data.error != "undefined" && data.error === "sessionSts1") {
      alert("מפגש זה הסתיים ולא מחוייב יותר . עליך ליזום מפגש חדש");
      window.location.href = "/";
    }
    if (data.type === "offer") {
      createPeerConnection();
      peerConnection.onicecandidate = function (event) {
        if (event.candidate) {
          const candidate = event.candidate;
          // Instead of sending immediately, queue the candidate if remote description is not yet set
          if (!peerConnection.remoteDescription) {
            iceCandidatesQueue.push(candidate);
          } else {
            socket.send(JSON.stringify({
              type: "candidate",
              user_guid: user_guid,
              room_guid: room_guid,
              user_type: user_type,
              data: candidate,
            }));
          }
        }
      };
      
      peerConnection.ontrack = function (event) {
        // Set the remote video source when a stream is received from the remote peer
        remoteVideo.srcObject = event.streams[0];
      
        // Check the types of tracks in the stream
        let hasAudio = event.streams[0].getAudioTracks().length > 0;
        let hasVideo = event.streams[0].getVideoTracks().length > 0;
      
        console.log("Received stream has audio:", hasAudio);
        console.log("Received stream has video:", hasVideo);
      
        // You can use hasAudio and hasVideo to adjust UI or handle the stream accordingly
        if (!hasVideo) {
          console.log("The stream contains only audio.");
          // Handle audio-only stream scenario
        } else if (!hasAudio) {
          console.log("The stream contains only video.");
          // Handle video-only stream scenario
        } else {
          console.log("The stream contains both audio and video.");
          // Handle scenario where both audio and video are present
        }
      };
      peerConnection.setRemoteDescription(
        new RTCSessionDescription(data.data)
      );
      const answer = peerConnection.createAnswer();
      peerConnection.setLocalDescription(answer);
      socket.send(
        JSON.stringify({
          type: "answer",
          user_guid: user_guid,
          room_guid: room_guid,
          user_type: user_type,
          data: answer,
        })
      );
      
    } 
    else if (data.type === "answer") {
      peerConnection.setRemoteDescription(
        new RTCSessionDescription(data.data)
      )
    
    }else if (data.type === "candidate") {
      peerConnection.addIceCandidate(new RTCIceCandidate(data.data));
    }
});


let iceCandidatesQueue = []; // Initialize an array to hold ICE candidates that arrive before the remote description is set.

function createPeerConnection() {
  peerConnection = new RTCPeerConnection(config);
  
    //with camera
    if (!localStream) {
      navigator.mediaDevices.getUserMedia({
        video: true,
        audio: true,
      })
      .then(stream => {
        localStream = stream;
        const localVideo = document.getElementById('localVideo');
        localVideo.srcObject = localStream;
         // Add each track from the local stream to the peer connection
        localStream.getTracks().forEach((track) => {
          peerConnection.addTrack(track, localStream);
        });
      })
      .catch(error => {
        alert("Unable to access the camera or microphone. Please try reloading the page.");
        console.error("getUserMedia error:", error);
      });
    }

 
}

// Starting a call (example)
async function startCall() {

  // socket.send(
  //   JSON.stringify({
  //     type: "offer",
  //     user_guid: user_guid,
  //     room_guid: room_guid,
  //     user_type: user_type,
  //     data: "startCall",
  //   })
  // );
}

function disconnect() {
  socket.send(
    JSON.stringify({
      type: "disconnect",
      user_guid: user_guid,
      room_guid: room_guid,
      user_type: user_type,
      data: "disconnect",
    })
  );
  console.log("Function called by worker at", new Date());
}
function update_time_use() {
    socket.send(
      JSON.stringify({
        type: "update",
        user_guid: user_guid,
        room_guid: room_guid,
        user_type: user_type,
        data: "update",
      })
    );
    //console.log("Function called by worker at", new Date());
}

setInterval(update_time_use, 3000); // Call doSomething every 3 seconds